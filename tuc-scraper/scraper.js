import { chromium } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const isTest = process.argv.includes('--test');
const outputFile = path.join(__dirname, isTest ? 'productos_test.json' : 'productos.json');

console.log('╔══════════════════════════════════════════╗');
console.log('║    TUSTECNOLOGÍATUC — LOCAL SCRAPER      ║');
console.log('╚══════════════════════════════════════════╝');
if (isTest) {
    console.log('🧪 MODO TEST ACTIVO: Se extraerán solo 5 productos a "productos_test.json"\n');
} else {
    console.log('🚀 MODO COMPLETO: Extrayendo catálogo completo a "productos.json"\n');
}

async function scrape() {
    const startTime = Date.now();

    console.log('🌐 Iniciando navegador Chromium (Playwright)...');
    const browser = await chromium.launch({
        headless: true,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-accelerated-2d-canvas',
            '--disable-gpu',
        ],
    });

    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        viewport: { width: 1280, height: 720 },
        extraHTTPHeaders: {
            'Accept-Language': 'es-AR,es;q=0.9,en;q=0.8',
        },
    });

    const page = await context.newPage();

    console.log('🔐 Conectando a tustecnologiastuc.com para validar Cloudflare...');
    await page.goto('https://www.tustecnologiastuc.com/tienda/', {
        waitUntil: 'domcontentloaded',
        timeout: 45000,
    });

    // Pequeña espera para resolver cookies de sesión
    await page.waitForTimeout(1500);
    console.log('✅ Sesión y cookies validadas con éxito.\n');

    const allProducts = [];
    let paged = 1;
    const itemsPerPage = 70;

    while (true) {
        process.stdout.write(`📄 Obteniendo Página ${paged}... `);

        const response = await page.evaluate(async ({ paged, itemsPerPage }) => {
            const formData = new URLSearchParams();
            formData.append('action', 'woodmart_get_products_shortcode');
            formData.append('woo_ajax', '1');
            formData.append('paged', String(paged));
            formData.append('atts[orderby]', 'date');
            formData.append('atts[pagination]', 'more-btn');
            formData.append('atts[items_per_page]', String(itemsPerPage));
            formData.append('atts[elementor]', 'true');
            formData.append('atts[columns][size]', '5');
            formData.append('atts[columns_tablet][size]', '3');
            formData.append('atts[columns_mobile][size]', '2');
            formData.append('atts[img_size]', 'custom');
            formData.append('atts[img_size_custom][width]', '339');
            formData.append('atts[img_size_custom][height]', '387');
            formData.append('atts[products_with_background]', '0');
            formData.append('atts[products_shadow]', '0');
            formData.append('atts[force_not_ajax]', 'no');

            const res = await fetch('https://www.tustecnologiastuc.com/wp-admin/admin-ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json, text/javascript, */*',
                },
                body: formData.toString(),
            });

            const status = res.status;
            let data = null;
            try {
                data = await res.json();
            } catch {}

            return { status, data };
        }, { paged, itemsPerPage });

        if (response.status !== 200 || !response.data || !response.data.items) {
            console.log(`❌ Error HTTP ${response.status}`);
            break;
        }

        const { items, status } = response.data;

        // Parsear productos de esta página usando el DOM del navegador
        const pageProducts = await page.evaluate((html) => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const nodes = doc.querySelectorAll('div.wd-product');

            const parsePrice = (str) => {
                if (!str) return null;
                const clean = str.replace(/&#36;|\$|\s/g, '').replace(/,/g, '');
                const val = parseFloat(clean);
                return isNaN(val) ? null : val;
            };

            const list = [];
            nodes.forEach((node) => {
                const externalId = node.getAttribute('data-id') || '';
                const titleEl = node.querySelector('h3.wd-entities-title a') || node.querySelector('a.wd-product-img-link');
                const nombre = titleEl ? titleEl.textContent.trim() : '';
                const urlProducto = titleEl ? titleEl.getAttribute('href') : '';

                if (!nombre) return;

                // Imagen (limpiando sufijos de tamaño de WordPress)
                const imgEl = node.querySelector('.wd-product-thumb img, .product-element-top img, img');
                let imagenUrl = '';
                if (imgEl) {
                    imagenUrl = imgEl.getAttribute('src')
                        || imgEl.getAttribute('data-src')
                        || imgEl.getAttribute('data-lazy-src')
                        || '';
                    if (!imagenUrl && imgEl.getAttribute('srcset')) {
                        const parts = imgEl.getAttribute('srcset').split(',');
                        if (parts.length > 0) {
                            imagenUrl = parts[0].trim().split(' ')[0];
                        }
                    }
                }
                if (imagenUrl) {
                    imagenUrl = imagenUrl.replace(/-\d+x\d+(?=\.(jpe?g|png|webp|gif|avif)$)/i, '');
                }

                // Precios
                const priceWrapper = node.querySelector('div.wrap-price, span.price');
                let precio = null;
                let precioAnterior = null;

                if (priceWrapper) {
                    const delEl = priceWrapper.querySelector('del');
                    const insEl = priceWrapper.querySelector('ins');

                    if (delEl && insEl) {
                        precioAnterior = parsePrice(delEl.textContent);
                        precio = parsePrice(insEl.textContent);
                    } else {
                        const amountEls = priceWrapper.querySelectorAll('span.woocommerce-Price-amount');
                        if (amountEls.length > 1) {
                            precioAnterior = parsePrice(amountEls[0].textContent);
                            precio = parsePrice(amountEls[1].textContent);
                        } else if (amountEls.length === 1) {
                            precio = parsePrice(amountEls[0].textContent);
                        }
                    }
                }

                // Stock
                let stock = null;
                const stockEl = node.querySelector('p.wd-product-stock');
                if (stockEl) {
                    const text = stockEl.textContent.trim();
                    const m = text.match(/(\d+)\s*(en stock|disponibles?|available|in stock)/i);
                    if (m) {
                        stock = parseInt(m[1], 10);
                    } else if (/sin stock|agotado|out of stock/i.test(text)) {
                        stock = 0;
                    }
                }
                if (stock === null) {
                    const inputQty = node.querySelector('input.qty[max]');
                    if (inputQty && inputQty.getAttribute('max')) {
                        const maxVal = parseInt(inputQty.getAttribute('max'), 10);
                        if (!isNaN(maxVal) && maxVal > 0) stock = maxVal;
                    }
                }

                // Categorías desde class: product_cat-{slug}
                const classAttr = node.getAttribute('class') || '';
                const catMatches = classAttr.matchAll(/\bproduct_cat-([a-z0-9\-]+)/gi);
                const categories = [];
                for (const match of catMatches) {
                    const catName = match[1]
                        .replace(/-/g, ' ')
                        .replace(/\b\w/g, (l) => l.toUpperCase());
                    if (!categories.includes(catName)) {
                        categories.push(catName);
                    }
                }
                const categoriaPrincipal = categories.length > 0 ? categories[0] : 'General';

                // SKU
                const addToCartBtn = node.querySelector('a.add_to_cart_button[data-product_sku]');
                const sku = addToCartBtn ? (addToCartBtn.getAttribute('data-product_sku') || '').trim() : '';

                // Descripción corta si viene en hover
                const descEl = node.querySelector('div.hover-content');
                const descripcion = descEl ? descEl.textContent.trim() : '';

                list.push({
                    external_id: externalId,
                    nombre,
                    descripcion,
                    precio: precio !== null ? String(precio) : '',
                    precio_anterior: precioAnterior !== null ? String(precioAnterior) : '',
                    imagen_url: imagenUrl,
                    url_producto: urlProducto,
                    categoria: categoriaPrincipal,
                    categorias: categories,
                    sku: sku || '',
                    stock: stock !== null ? String(stock) : '',
                });
            });

            return list;
        }, items);

        console.log(`✅ ${pageProducts.length} productos extraídos.`);

        if (isTest) {
            allProducts.push(...pageProducts.slice(0, 5));
            break;
        }

        allProducts.push(...pageProducts);

        if (status === 'no-more-posts' || pageProducts.length === 0) {
            console.log('\n🏁 Fin de productos (no-more-posts).');
            break;
        }

        paged++;
        await page.waitForTimeout(600); // 600ms de respiro entre páginas
    }

    await browser.close();

    // Guardar archivo JSON
    fs.writeFileSync(outputFile, JSON.stringify(allProducts, null, 2), 'utf-8');

    const durationSec = ((Date.now() - startTime) / 1000).toFixed(2);
    console.log('\n╔══════════════════════════════════════════╗');
    console.log('║           RESUMEN FINAL                  ║');
    console.log('╚══════════════════════════════════════════╝');
    console.log(`📦 Total productos:   ${allProducts.length}`);
    console.log(`⏱️  Tiempo total:      ${durationSec}s`);
    console.log(`💾 Archivo guardado:  ${outputFile}\n`);

    if (isTest && allProducts.length > 0) {
        console.log('🔍 MUESTRA DE LOS 5 PRODUCTOS EXTRAÍDOS:\n');
        allProducts.forEach((p, idx) => {
            console.log(`[${idx + 1}] ${p.nombre}`);
            console.log(`    • Precio:    $${p.precio}${p.precio_anterior ? ` (Antes: $${p.precio_anterior})` : ''}`);
            console.log(`    • Categoría: ${p.categoria}`);
            console.log(`    • Stock:     ${p.stock || 'No especificado'}`);
            console.log(`    • Imagen:    ${p.imagen_url}`);
            console.log(`    • URL:       ${p.url_producto}\n`);
        });
    }
}

scrape().catch((err) => {
    console.error('❌ Error fatal en el scraper:', err);
    process.exit(1);
});

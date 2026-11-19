import { chromium } from '@playwright/test';

function parseArgs() {
    const args = process.argv.slice(2);
    const parsed = {
        page: 1,
        maxPages: null,
        itemsPerPage: 70,
        proxy: null,
        timeout: 40000,
    };

    for (const arg of args) {
        if (arg.startsWith('--page=')) {
            parsed.page = parseInt(arg.split('=')[1], 10) || 1;
        } else if (arg.startsWith('--max-pages=')) {
            parsed.maxPages = parseInt(arg.split('=')[1], 10) || null;
        } else if (arg.startsWith('--items-per-page=')) {
            parsed.itemsPerPage = parseInt(arg.split('=')[1], 10) || 70;
        } else if (arg.startsWith('--proxy=')) {
            parsed.proxy = arg.split('=')[1];
        } else if (arg.startsWith('--timeout=')) {
            parsed.timeout = parseInt(arg.split('=')[1], 10) || 40000;
        }
    }
    return parsed;
}

async function main() {
    const config = parseArgs();
    const launchOptions = {
        headless: true,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-accelerated-2d-canvas',
            '--disable-gpu',
        ],
    };

    if (config.proxy) {
        launchOptions.proxy = { server: config.proxy };
    }

    let browser;
    try {
        browser = await chromium.launch(launchOptions);
    } catch (launchErr) {
        process.stderr.write(JSON.stringify({
            success: false,
            error: 'PLAYWRIGHT_LAUNCH_FAILED: ' + launchErr.message,
        }) + '\n');
        process.exit(2);
    }

    try {
        const userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';
        const context = await browser.newContext({
            userAgent,
            viewport: { width: 1280, height: 720 },
            extraHTTPHeaders: {
                'Accept-Language': 'es-AR,es;q=0.9,en;q=0.8',
            },
        });

        const page = await context.newPage();

        // 1) Navegar a tustecnologiastuc.com para resolver el challenge de Cloudflare
        await page.goto('https://www.tustecnologiastuc.com/tienda/', {
            waitUntil: 'domcontentloaded',
            timeout: config.timeout,
        });

        // Breve espera para que Cloudflare y analytics asienten cookies
        await page.waitForTimeout(1500);

        const cookies = await context.cookies();
        const cookieString = cookies.map(c => `${c.name}=${c.value}`).join('; ');

        // Función que ejecuta el POST dentro del contexto del browser
        const fetchAjaxPage = async (paged) => {
            return await page.evaluate(async ({ paged, itemsPerPage }) => {
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
                let rawText = '';
                try {
                    data = await res.json();
                } catch {
                    rawText = await res.text();
                }

                return { status, data, rawText };
            }, { paged, itemsPerPage: config.itemsPerPage });
        };

        // Si se pidió solo una página específica
        if (config.maxPages === null) {
            const result = await fetchAjaxPage(config.page);
            await browser.close();

            if (result.status !== 200 || !result.data) {
                process.stderr.write(JSON.stringify({
                    success: false,
                    status: result.status,
                    error: result.rawText ? result.rawText.substring(0, 300) : 'Sin JSON en la respuesta',
                    cookies: cookieString,
                    userAgent,
                }) + '\n');
                process.exit(1);
            }

            process.stdout.write(JSON.stringify({
                success: true,
                status: result.status,
                page: config.page,
                data: result.data,
                cookies: cookieString,
                userAgent,
            }));
            process.exit(0);
        }

        // Si se pidió un batch de páginas (stream de NDJSON)
        let currentPage = config.page;
        let fetchedCount = 0;

        while (true) {
            if (config.maxPages !== null && fetchedCount >= config.maxPages) {
                break;
            }

            const result = await fetchAjaxPage(currentPage);
            if (result.status !== 200 || !result.data) {
                process.stderr.write(JSON.stringify({
                    success: false,
                    page: currentPage,
                    status: result.status,
                    error: result.rawText ? result.rawText.substring(0, 300) : 'Error en respuesta',
                }) + '\n');
                break;
            }

            process.stdout.write(JSON.stringify({
                success: true,
                status: result.status,
                page: currentPage,
                data: result.data,
            }) + '\n');

            fetchedCount++;
            if (result.data.status === 'no-more-posts' || !result.data.items) {
                break;
            }

            currentPage++;
            await page.waitForTimeout(500); // 500ms entre requests dentro de la sesión
        }

        await browser.close();
        process.exit(0);

    } catch (err) {
        if (browser) {
            await browser.close().catch(() => {});
        }
        process.stderr.write(JSON.stringify({
            success: false,
            error: err.message,
        }) + '\n');
        process.exit(1);
    }
}

main();

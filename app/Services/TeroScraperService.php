<?php

namespace App\Services;

use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Scraper de productos desde teromatero.com.ar
 *
 * Plataforma: Tiendanube / Nuvemshop (tema "lima").
 * Devuelve HTML completo del servidor (no usa AJAX), paginación tradicional `/page/N/`.
 *
 * Estructura de cada card `<div class="js-item-product">`:
 *  - data-product-id="293255196"   → external_id (interno de Tiendanube)
 *  - <a href="/productos/slug/">   → source_url
 *  - <img srcset="...">            → imagen principal
 *  - <span class="js-item-name">   → nombre
 *  - <span class="js-price-display">          → precio actual (con transferencia si hay descuento)
 *  - <span class="js-compare-price-display">  → precio lista tachado (oculto si no hay descuento)
 *
 * Diferencia de precios con Daz/Tuc:
 *  - js-compare-price-display es el precio LISTA (mayor, tachado)
 *  - js-price-display es el precio con transferencia (menor) o único si no hay promo
 *  - Si js-compare-price-display está oculto (style="display:none") o es "$0,00"
 *    → no hay descuento → cash_price = null, list_price = js-price-display
 *  - Si está visible → list_price = js-compare-price-display, cash_price = js-price-display
 */
class TeroScraperService extends AbstractProductScraper
{
    protected function configure(): void
    {
        $this->baseUrl  = 'https://www.teromatero.com.ar';
        $this->origin   = 'tero';
        $this->referer  = 'https://www.teromatero.com.ar/productos/';
        $this->shopPath = '/productos/';
    }

    // ============================================================
    //  fetchPage
    // ============================================================

    public function fetchPage(string $url): array
    {
        $body = $this->httpGet($url);

        [, $xpath] = $this->loadHtmlFragment($body);

        // Las cards tienen clase `js-item-product` y `item-product`
        $cardNodes = $xpath->query('//div[contains(@class, "js-item-product") and contains(@class, "item-product")]');
        if ($cardNodes === false) {
            $cardNodes = $xpath->query('//div[contains(@class, "js-item-product")]');
        }

        $products = [];
        if ($cardNodes !== false) {
            foreach ($cardNodes as $node) {
                $parsed = $this->parseProductNode($xpath, $node);
                if ($parsed !== null) {
                    $products[] = $parsed;
                }
            }
        }

        // ⚠️ Si la página no tiene productos, cortamos el loop devolviendo
        // nextPage=null. Esto es crucial para no entrar en loop infinito
        // cuando el sitio devuelve una página vacía (ej. /page/13/ cuando
        // solo hay 12 páginas con productos).
        $nextPage = empty($products)
            ? null
            : $this->extractNextPageUrl($xpath, $url);

        return [
            'products' => $products,
            'nextPage' => $nextPage,
            'total'    => $this->extractTotalProducts($xpath),
        ];
    }

    /**
     * Detecta si hay un link "siguiente" en la paginación.
     * Si lo hay, devuelve su URL absoluta. Si no, devuelve una URL
     * calculada a partir de la URL actual (con fallback que cubre
     * tanto /page/N/ como la primera página /productos/).
     */
    private function extractNextPageUrl(DOMXPath $xpath, string $currentUrl): ?string
    {
        // 1) Buscar un link "next" claro en el DOM
        $candidates = [
            '//a[contains(@class, "js-pagination-next") and @href]',
            '//a[contains(@class, "page-next") and @href]',
            '//a[@rel="next" and @href]',
            '//a[contains(@aria-label, "Siguiente") and @href]',
            '//a[contains(@aria-label, "Next") and @href]',
        ];
        foreach ($candidates as $sel) {
            $node = $this->firstOf($xpath, $sel);
            if ($node instanceof DOMElement) {
                $href = $node->getAttribute('href');
                if ($href !== '') {
                    return $this->resolveUrl($href);
                }
            }
        }

        // 2) Fallback: calcular la siguiente URL a partir de la actual
        //    - Si la URL actual es /page/N/ → siguiente es /page/(N+1)/
        //    - Si la URL actual es la primera (/productos/ sin /page/) → siguiente es /page/2/
        if (preg_match('#/page/(\d+)/?$#', $currentUrl, $m)) {
            $nextPageNum = ((int) $m[1]) + 1;
            return preg_replace('#/page/\d+/?$#', '/page/' . $nextPageNum . '/', $currentUrl);
        }

        // Primera página: armar /page/2/ desde el shopPath
        return rtrim($this->baseUrl, '/') . $this->shopPath . 'page/2/';
    }

    /**
     * Extrae el total de productos del HTML.
     * Tiendanube suele mostrar "Mostrando 1-24 de 234 productos" o un count en el sidebar.
     */
    private function extractTotalProducts(DOMXPath $xpath): ?int
    {
        $candidates = [
            '//*[contains(@class, "js-product-count") and string-length(text()) > 0]',
            '//*[contains(@class, "results-count") and string-length(text()) > 0]',
            '//*[contains(@class, "js-pagination-total") and string-length(text()) > 0]',
        ];
        foreach ($candidates as $sel) {
            $node = $this->firstOf($xpath, $sel);
            if ($node instanceof DOMElement) {
                $text = $this->cleanText($node->textContent);
                if ($text !== null && preg_match('/(\d+)/', $text, $m)) {
                    return (int) $m[1];
                }
            }
        }

        // Otra estrategia: contar todos los data-product-id únicos
        $ids = $xpath->query('//*[@data-product-id]');
        if ($ids !== false && $ids->length > 0) {
            $unique = [];
            foreach ($ids as $n) {
                $id = $n->getAttribute('data-product-id');
                if ($id !== '') {
                    $unique[$id] = true;
                }
            }
            if (count($unique) > 0) {
                return count($unique);
            }
        }

        return null;
    }

    // ============================================================
    //  parseProductNode
    // ============================================================

    protected function parseProductNode(DOMXPath $xpath, DOMNode $node): ?array
    {
        if (! $node instanceof DOMElement) {
            return null;
        }

        $rawExternalId = $node->getAttribute('data-product-id');
        if ($rawExternalId === '') {
            return null;
        }
        $externalId = 'tero-' . $rawExternalId;

        // Nombre + source_url
        $name = null;
        $sourceUrl = null;
        $nameNode = $this->firstOf($xpath, './/*[contains(@class, "js-item-name")]', $node);
        if ($nameNode instanceof DOMElement) {
            $name = $this->cleanText($nameNode->textContent);
        }

        // El link del producto: cualquier <a href="/productos/..."> dentro de la card
        $linkNodes = $xpath->query('.//a[@href]', $node);
        if ($linkNodes !== false) {
            foreach ($linkNodes as $ln) {
                /** @var DOMElement $ln */
                $href = $ln->getAttribute('href');
                if ($href !== '' && str_contains($href, '/productos/')) {
                    $sourceUrl = $this->resolveUrl($href);
                    break;
                }
            }
        }

        if ($name === null || $name === '') {
            return null;
        }

        $image   = $this->extractImage($xpath, $node);
        $prices  = $this->extractPrices($xpath, $node);

        return [
            'external_id'         => $externalId,
            'name'                => $name,
            'image'               => $image,
            'sku'                 => null, // Tiendanube no expone SKU en la card
            'stock'               => null, // Tiendanube no muestra stock en la card
            'list_price'          => $prices['list_price'],
            'cash_price'          => $prices['cash_price'],
            'price'               => $prices['cash_price'] ?? $prices['list_price'],
            'brand'               => null, // No se ve marca en la card
            'source_url'          => $sourceUrl,
            'categories_external' => $this->extractCategories($xpath, $node, $name),
            'origin'              => $this->origin,
        ];
    }

    // ============================================================
    //  Hooks específicos de Tero
    // ============================================================

    /**
     * Imagen principal: Tiendanube usa lazysizes, así que la URL real
     * suele estar en `data-srcset` (con `src` siendo un GIF placeholder).
     * Prioridad:
     *   1) data-srcset (lazy load de lazysizes — caso más común)
     *   2) srcset    (imágenes ya cargadas, o segunda imagen de hover)
     *   3) data-src  (algunos templates)
     *   4) src       (último fallback, descartando placeholders GIF)
     *
     * Cuando hay srcset, tomamos la URL más grande (la última de la lista,
     * ya que Tiendanube las ordena de menor a mayor resolución).
     */
    protected function extractImage(DOMXPath $xpath, DOMNode $node): ?string
    {
        $imgNode = $this->firstOf(
            $xpath,
            './/img[contains(@class, "js-item-image") or contains(@class, "js-product-slide-img")]',
            $node
        );
        if (! $imgNode instanceof DOMElement) {
            $imgNode = $this->firstOf($xpath, './/img', $node);
        }
        if (! $imgNode instanceof DOMElement) {
            return null;
        }

        $dataSrcset = $imgNode->getAttribute('data-srcset');
        $srcset     = $imgNode->getAttribute('srcset');
        $dataSrc    = $imgNode->getAttribute('data-src');
        $src        = $imgNode->getAttribute('src');

        // Filtrar src si es el placeholder GIF de lazysizes
        $isPlaceholder = static function (string $url): bool {
            return str_starts_with($url, 'data:image/gif') || $url === '';
        };

        $rawImage = null;

        if ($dataSrcset !== '') {
            $rawImage = $this->pickLargestFromSrcset($dataSrcset);
        }
        if ($rawImage === null && $srcset !== '') {
            $rawImage = $this->pickLargestFromSrcset($srcset);
        }
        if ($rawImage === null && $dataSrc !== '' && !$isPlaceholder($dataSrc)) {
            $rawImage = $dataSrc;
        }
        if ($rawImage === null && !$isPlaceholder($src)) {
            $rawImage = $src;
        }

        if ($rawImage === null) {
            return null;
        }

        $image = $this->resolveUrl($rawImage);
        if ($image === null) {
            return null;
        }

        // Limpiar sufijo de tamaño de Tiendanube (-640-0-HASH-480-0.ext)
        $image = $this->stripTiendaNubeSizeSuffix($image);

        // Validar que parezca imagen
        if (preg_match('/\.(jpg|jpeg|png|webp|gif|avif)(\?|$)/i', $image)) {
            return $image;
        }

        return null;
    }

    /**
     * Dado un srcset (lista separada por coma con descriptores "100w" o "2x"),
     * devuelve la URL de mayor resolución (la última en orden ascendente).
     */
    private function pickLargestFromSrcset(string $srcset): ?string
    {
        $parts = array_map('trim', explode(',', $srcset));
        $parts = array_filter($parts, static fn ($p) => $p !== '');
        if (empty($parts)) {
            return null;
        }
        $last = (string) end($parts);
        // "URL 100w" o "URL 2x" → agarrar la URL
        if (preg_match('/^(\S+)/', $last, $m)) {
            return $m[1];
        }
        return $last;
    }

    /**
     * Precios: list_price (js-compare-price-display si está visible y no es $0)
     *          cash_price (js-price-display cuando hay descuento, sino null)
     *
     * Detección de "hay descuento":
     *  - js-compare-price-display tiene style distinto a "display:none"
     *  - Y su texto parseado es > 0
     */
    protected function extractPrices(DOMXPath $xpath, DOMNode $node): array
    {
        $listPrice = null;
        $cashPrice = null;

        $currentRaw = null;
        $priceNode = $this->firstOf($xpath, './/*[contains(@class, "js-price-display")]', $node);
        if ($priceNode instanceof DOMElement) {
            $currentRaw = $this->cleanText($priceNode->textContent);
        }
        $currentAmount = $currentRaw !== null ? $this->parsePriceAmount($currentRaw) : null;

        $compareRaw = null;
        $compareVisible = false;
        $compareNode = $this->firstOf($xpath, './/*[contains(@class, "js-compare-price-display")]', $node);
        if ($compareNode instanceof DOMElement) {
            $compareRaw = $this->cleanText($compareNode->textContent);
            $style = $compareNode->getAttribute('style');
            $compareVisible = $style !== '' && !str_contains($style, 'display:none');
        }
        $compareAmount = $compareRaw !== null ? $this->parsePriceAmount($compareRaw) : null;

        if ($compareAmount !== null && $compareAmount > 0 && $compareVisible) {
            // Hay descuento: compare = lista (mayor), current = con transferencia (menor)
            $listPrice = $compareAmount;
            $cashPrice = ($currentAmount !== null && $currentAmount < $compareAmount)
                ? $currentAmount
                : null;
        } else {
            // Sin descuento: solo hay un precio (el principal)
            $listPrice = $currentAmount;
            $cashPrice = null;
        }

        return [
            'list_price' => $listPrice,
            'cash_price' => $cashPrice,
        ];
    }

    /**
     * Categoría: Teromatero no muestra la categoría en la card.
     * La categoría real se obtiene de la página de detalle vía
     * fetchProductDetail() → extractBreadcrumbs().
     * Acá dejamos un hint por nombre como fallback si la card se guarda
     * sin pasar por el detail-pass.
     */
    protected function extractCategories(DOMXPath $xpath, DOMNode $node, ?string $productName = null): array
    {
        if ($productName === null) {
            return [];
        }
        $lower = mb_strtolower($productName);
        $hints = [];
        if (str_contains($lower, 'mate') || str_contains($lower, 'terero') || str_contains($lower, 'teromatero')) {
            $hints[] = 'Mates';
        }
        if (str_contains($lower, 'bazar') || str_contains($lower, 'vaso') || str_contains($lower, 'taza')) {
            $hints[] = 'Bazar';
        }
        return $hints;
    }

    // ============================================================
    //  Detail-pass: scrapea la página individual de cada producto
    // ============================================================

    /**
     * Descarga y parsea la página de detalle de UN producto.
     *
     * Devuelve:
     *  - stock         int|null   unidades disponibles (del JSON-LD inventoryLevel,
     *                             con fallback a la clase del botón si no hay JSON-LD)
     *  - availability  bool|null  true si está disponible, false si no
     *  - description   string|null descripción completa (viene del JSON-LD; si Tiendanube
     *                             no la expone, queda null)
     *  - category_path array      ej: ['Bazar', 'Organizadores'] — desde los breadcrumbs
     *  - gallery       array      URLs completas de la galería de imágenes
     *  - sku           string|null
     *  - weight        float|null en Kg
     *
     * Nota: la descripción puede no aparecer en el HTML de Tiendanube (es JS-pesado
     * y la descripción se renderiza client-side). Solo la guardamos si está
     * disponible en el JSON-LD.
     *
     * @return array{
     *   stock: int|null,
     *   availability: bool|null,
     *   description: string|null,
     *   category_path: array<int,string>,
     *   gallery: array<int,string>,
     *   sku: string|null,
     *   weight: float|null,
     *   stock_source: 'jsonld'|'button'|'inferred'|null
     * }
     */
    public function fetchProductDetail(string $url): array
    {
        $body = $this->httpGet($url);
        [, $xpath] = $this->loadHtmlFragment($body);

        $jsonLd   = $this->extractJsonLdProduct($xpath, $url);
        $category = $this->extractBreadcrumbs($xpath);
        $gallery  = $this->extractGallery($xpath);

        // 1) Stock: del JSON-LD si está disponible
        $stock        = $this->parseInventoryLevel($jsonLd['offers']['inventoryLevel'] ?? null);
        $availability = $this->parseAvailability($jsonLd['offers']['availability'] ?? null);
        $stockSource  = $stock !== null ? 'jsonld' : null;

        // 1b) Fallback prioritario: JSON embebido en el HTML con stock real.
        //     Teromatero inyecta un JSON con el variant seleccionado como
        //     parte del JS del carrito. El campo "stock" de ese JSON es
        //     el stock REAL (no placeholder, no del botón). Lo buscamos
        //     en el body raw (HTML-encoded con \").
        if ($stock === null) {
            $htmlStock = $this->extractStockFromHtml($body);
            if ($htmlStock !== null) {
                $stock = $htmlStock;
                $stockSource = 'html_json';
                $availability = $htmlStock > 0;
            }
        }

        // 2) Fallback: si no hay JSON-LD, usar la clase del botón
        if ($stock === null) {
            $btnClasses = $this->detectButtonClasses($xpath);
            if (in_array('nostock', $btnClasses, true)) {
                $stock = 0;
                $availability = false;
                $stockSource = 'button';
            } elseif (in_array('cart', $btnClasses, true)) {
                // Con stock, pero no sabemos cuánto. Usamos 10 como valor por defecto.
                // La idea: si después baja el stock, lo bajamos; si no, queda en 10
                // (que es >0, el producto aparece disponible).
                $stock = 10;
                $availability = true;
                $stockSource = 'button';
            } elseif (in_array('contact', $btnClasses, true)) {
                $stock = 0;
                $availability = false;
                $stockSource = 'button';
            }
        }

        $description  = $this->cleanText($jsonLd['description'] ?? null);
        $sku          = $this->cleanText($jsonLd['sku'] ?? null);
        $weight       = isset($jsonLd['weight']['value']) ? (float) $jsonLd['weight']['value'] : null;

        return [
            'stock'         => $stock,
            'availability'  => $availability,
            'description'   => $description,
            'category_path' => $category,
            'gallery'       => $gallery,
            'sku'           => $sku,
            'weight'        => $weight,
            'stock_source'  => $stockSource,
        ];
    }

    /**
     * Detecta las clases del botón de agregar al carrito.
     * Devuelve ['cart'] si tiene stock, ['nostock'] si no, ['contact'] si es "consultar", [] si nada.
     *
     * @return array<int,string>
     */
    private function detectButtonClasses(DOMXPath $xpath): array
    {
        $btn = $this->firstOf($xpath, '//*[contains(@class, "js-addtocart") and contains(@class, "js-prod-submit-form")]');
        if (! $btn instanceof DOMElement) {
            return [];
        }
        $cls = $btn->getAttribute('class');
        $result = [];
        if (str_contains($cls, 'nostock')) $result[] = 'nostock';
        if (str_contains($cls, 'cart')) $result[] = 'cart';
        if (str_contains($cls, 'contact')) $result[] = 'contact';
        return $result;
    }

    /**
     * Extrae el JSON-LD principal del producto (no el de productos relacionados).
     * Busca el Product cuyo `offers.url` coincida con la URL de la página actual.
     *
     * ⚠️ Si no encuentra match por URL, devuelve array vacío (NO agarra un
     * producto relacionado, que es lo que estaba pasando antes y producía
     * descripciones/sku/weight incorrectos).
     */
    private function extractJsonLdProduct(DOMXPath $xpath, string $currentUrl): array
    {
        $ldScripts = $xpath->query('//script[@type="application/ld+json"]');
        if ($ldScripts === false) {
            return [];
        }

        // Normalizar la URL actual para comparar (sin trailing slash, etc.)
        $normalizedCurrent = rtrim($currentUrl, '/');

        foreach ($ldScripts as $s) {
            $raw = trim($s->textContent);
            if ($raw === '') continue;
            $data = json_decode($raw, true);
            if (!is_array($data)) continue;

            $products = [];
            if (isset($data['@graph']) && is_array($data['@graph'])) {
                foreach ($data['@graph'] as $item) {
                    if (($item['@type'] ?? '') === 'Product') {
                        $products[] = $item;
                    }
                }
            } elseif (($data['@type'] ?? '') === 'Product') {
                $products[] = $data;
            }

            foreach ($products as $product) {
                $offerUrl = $product['offers']['url'] ?? '';
                $normalizedOffer = rtrim($offerUrl, '/');
                if ($normalizedOffer === $normalizedCurrent) {
                    return $product;
                }
            }
        }

        // No se encontró un Product con offers.url que matchee la URL actual.
        // Devolvemos array vacío para no tomar datos de un producto relacionado.
        return [];
    }

    /**
     * Extrae el path de categorías desde los breadcrumbs.
     * Ej: Inicio > Bazar > Organizadores → ['Bazar', 'Organizadores']
     * Filtra "Inicio", "Productos" y similares.
     */
    private function extractBreadcrumbs(DOMXPath $xpath): array
    {
        $path = [];

        // Estrategia 1: nav con class breadcrumb
        $crumbNodes = $xpath->query('//nav[contains(@class, "breadcrumb")]//a');
        if ($crumbNodes === false || $crumbNodes->length === 0) {
            // Estrategia 2: cualquier contenedor con class breadcrumb
            $crumbNodes = $xpath->query('//*[contains(@class, "breadcrumb")]//a');
        }

        if ($crumbNodes === false) {
            return [];
        }

        $skip = ['inicio', 'home', 'productos', 'products', 'todos los productos'];
        foreach ($crumbNodes as $node) {
            /** @var DOMElement $node */
            $text = $this->cleanText($node->textContent);
            if ($text === null) continue;
            if (in_array(mb_strtolower($text), $skip, true)) continue;
            // Filtrar paths obvios que no son categorías (ej: "Souvenirs & Eventos")
            if (mb_strlen($text) > 50) continue;
            $path[] = $text;
        }

        return $path;
    }

    /**
     * Extrae la galería completa de imágenes del producto.
     * Busca en `<img class="js-product-slide-img">` que es donde Tiendanube
     * pone la galería. Devuelve URLs únicas en la mayor resolución disponible.
     *
     * @return array<int,string>
     */
    private function extractGallery(DOMXPath $xpath): array
    {
        $gallery = [];

        // 1) Galería principal: js-product-slide-img
        $imgNodes = $xpath->query('//img[contains(@class, "js-product-slide-img")]');

        if ($imgNodes !== false) {
            foreach ($imgNodes as $img) {
                /** @var DOMElement $img */
                $dataSrcset = $img->getAttribute('data-srcset');
                $srcset     = $img->getAttribute('srcset');
                $url = $this->pickLargestFromSrcset($dataSrcset) ?? $this->pickLargestFromSrcset($srcset);
                if ($url === null) {
                    $dataSrc = $img->getAttribute('data-src');
                    $src     = $img->getAttribute('src');
                    $url = ($dataSrc !== '' && !str_starts_with($dataSrc, 'data:')) ? $dataSrc
                        : (($src !== '' && !str_starts_with($src, 'data:')) ? $src : null);
                }
                if ($url !== null && str_contains($url, 'mitiendanube')) {
                    $url = $this->resolveUrl($url);
                    $url = $this->stripTiendaNubeSizeSuffix((string) $url);
                    if (preg_match('/\.(jpg|jpeg|png|webp|gif|avif)(\?|$)/i', $url)) {
                        $gallery[] = $url;
                    }
                }
            }
        }

        // 2) Fallback: cualquier img dentro del contenedor de producto
        if (empty($gallery)) {
            $container = $this->firstOf($xpath, '//*[contains(@class, "js-product-images")]');
            if ($container instanceof DOMElement) {
                $imgs = $xpath->query('.//img', $container);
                if ($imgs !== false) {
                    foreach ($imgs as $img) {
                        /** @var DOMElement $img */
                        $dataSrc = $img->getAttribute('data-src');
                        $src     = $img->getAttribute('src');
                        $url = ($dataSrc !== '' && !str_starts_with($dataSrc, 'data:')) ? $dataSrc : $src;
                        if ($url !== null && $url !== '' && str_contains($url, 'mitiendanube')) {
                            $url = $this->resolveUrl($url);
                            $url = $this->stripTiendaNubeSizeSuffix((string) $url);
                            if (preg_match('/\.(jpg|jpeg|png|webp|gif|avif)(\?|$)/i', $url)) {
                                $gallery[] = $url;
                            }
                        }
                    }
                }
            }
        }

        // Quitar duplicados preservando orden
        $unique = [];
        foreach ($gallery as $g) {
            if (!in_array($g, $unique, true)) {
                $unique[] = $g;
            }
        }

        return $unique;
    }

    /**
     * Parsea `inventoryLevel` del JSON-LD.
     * Puede venir como { "value": "4" } o como un array.
     */
    private function parseInventoryLevel(mixed $raw): ?int
    {
        if ($raw === null) return null;
        $value = is_array($raw) ? ($raw['value'] ?? null) : $raw;
        if ($value === null) return null;
        if (is_numeric($value)) {
            $n = (int) $value;
            return $n >= 0 ? $n : null;
        }
        return null;
    }

    /**
     * Parsea `availability` del JSON-LD.
     * Devuelve true si está InStock, false si OutOfStock, null si no se puede determinar.
     */
    private function parseAvailability(mixed $raw): ?bool
    {
        if (!is_string($raw)) return null;
        $lower = mb_strtolower($raw);
        if (str_contains($lower, 'instock')) return true;
        if (str_contains($lower, 'outofstock')) return false;
        if (str_contains($lower, 'discontinued')) return false;
        return null;
    }

    /**
     * Busca el stock REAL del producto en el HTML crudo.
     *
     * Teromatero inyecta un objeto JSON con la info del variant seleccionado
     * como parte del JS del carrito. El campo "stock" de ese objeto es el
     * stock real (no placeholder, no "cart"/"nostock"). El JSON está
     * HTML-encoded con `\"` (escaped), por eso no lo agarra DOMDocument.
     *
     * Patrón encontrado:
     *   ...,"compare_at_price_long":null,...,"stock":3,"sku":null,"available":true,...
     *
     * Devuelve el número del stock, o null si no se pudo parsear.
     */
    private function extractStockFromHtml(string $body): ?int
    {
        // Buscamos un objeto {...} que contenga "stock":NUMBER dentro de
        // comillas escapadas. Limitamos el rango para no caer en JSON
        // anidados de otros productos relacionados.
        if (preg_match('/\{[^{}]{0,500}\"stock\"\s*:\s*(\d+)[^{}]{0,500}\}/', $body, $m)) {
            $value = (int) $m[1];
            return $value >= 0 ? $value : null;
        }

        // Fallback: buscar en HTML-encoded
        if (preg_match('/&quot;stock&quot;\s*:\s*(\d+)/', $body, $m)) {
            $value = (int) $m[1];
            return $value >= 0 ? $value : null;
        }

        return null;
    }
}

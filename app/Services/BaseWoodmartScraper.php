<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Base scraper para sitios WooCommerce con tema Woodmart
 * (compatible con dazimportadora.com.ar, tustecnologiastuc.com, etc.).
 *
 * Estructura esperada de la respuesta AJAX:
 *  - items:     HTML con todos los <div class="wd-product ..."> de la página
 *  - nextPage:  URL a la próxima página (null si es la última)
 *  - currentPage: URL de la página actual
 *  - resultCount: "Mostrando X–Y de Z resultados"
 *
 * Cada <div class="wd-product"> contiene:
 *  - data-id="12345"                       → external_id
 *  - h3 > a                                → nombre + source_url
 *  - img.attachment-large                  → imagen principal
 *  - span.gtm4wp_productdata               → JSON con sku, price, stocklevel
 *  - .wd-product-cats a   (opcional)       → categorías
 *  - .wd-product-brands-links a (opcional) → marca
 *  - clases product_cat-* en el root       → categorías (formato Tuc)
 *
 * Las subclases solo configuran el sitio y (si hace falta) sobreescriben
 * los hooks `extractCategories()` y `extractBrand()`.
 *
 * Hereda de AbstractProductScraper el loop de páginas, los helpers HTTP,
 * y los helpers de parsing (resolveUrl, cleanText, firstOf, parsePriceAmount).
 */
abstract class BaseWoodmartScraper extends AbstractProductScraper
{
    /**
     * Prefijo para los external_id (las subclases definen el suyo).
     *  - Daz:  '' (vacío, usa el data-id directo)
     *  - Tuc:  '' (vacío, usa el data-id directo)
     */
    protected string $externalIdPrefix = '';

    /**
     * Parámetros por defecto del AJAX de WooCommerce.
     */
    protected array $defaultAtts = [
        'product_hover_type'          => 'predefined',
        'product_custom_hover'        => '122488',
        'img_size'                    => 'large',
        'img_size_custom'             => '0',
        'products_view'               => 'grid',
        'products_columns'            => '5',
        'products_columns_tablet'     => '4',
        'products_columns_mobile'     => '2',
        'products_spacing'            => '20',
        'products_spacing_tablet'     => '',
        'products_spacing_mobile'     => '',
        'products_list_spacing'       => '30',
        'products_list_spacing_tablet' => '',
        'products_list_spacing_mobile' => '',
        'product_hover'               => 'fw-button',
        'products_bordered_grid'      => '0',
        'products_bordered_grid_style' => 'outside',
        'products_color_scheme'       => 'default',
        'products_with_background'    => '1',
        'products_shadow'             => '0',
    ];

    // ============================================================
    //  fetchPage (específico de Woodmart AJAX)
    // ============================================================

    /**
     * Descarga UNA página AJAX y parsea sus productos.
     *
     * @return array{products: array<int, array<string,mixed>>, nextPage: ?string, total: ?int}
     */
    public function fetchPage(string $url): array
    {
        $requestUrl = $this->buildRequestUrl($url);

        $response = Http::withHeaders($this->getHeaders())
            ->withOptions([
                'allow_redirects' => true,
                'timeout' => 30,
                'connect_timeout' => 10,
            ])
            ->get($requestUrl);

        if (! $response->successful()) {
            throw new RuntimeException(
                "HTTP {$response->status()} al solicitar {$requestUrl}"
            );
        }

        $body = $response->body();
        $data = json_decode($body, true);

        if (! is_array($data) || ! isset($data['items'])) {
            throw new RuntimeException(
                'Respuesta no es JSON válido o no tiene campo "items". ' .
                'Primeros 200 chars: ' . substr($body, 0, 200)
            );
        }

        $products = $this->parseProductsFromHtml($data['items']);

        return [
            'products' => $products,
            'nextPage' => $data['nextPage'] ?? null,
            'total'    => $this->extractTotalProducts($data),
        ];
    }

    /**
     * Extrae el total de productos del campo `resultCount` del AJAX.
     */
    protected function extractTotalProducts(array $data): ?int
    {
        $candidates = [
            $data['resultCount'] ?? null,
            $data['total']       ?? null,
            $data['foundPosts']  ?? null,
        ];

        foreach ($candidates as $raw) {
            if ($raw === null) {
                continue;
            }
            if (is_numeric($raw)) {
                return (int) $raw;
            }
            if (is_string($raw) && preg_match('/de\s+(\d+)\s+result/i', $raw, $m)) {
                return (int) $m[1];
            }
            if (is_string($raw) && preg_match('/(\d+)\s+productos?/i', $raw, $m)) {
                return (int) $m[1];
            }
        }

        return null;
    }

    /**
     * Construye la URL del AJAX de WooCommerce con todos los parámetros atts[...].
     */
    private function buildRequestUrl(string $pageUrl): string
    {
        if (str_contains($pageUrl, 'woo_ajax=1')) {
            return $pageUrl;
        }

        $params = ['loop' => 35, 'woo_ajax' => 1];
        foreach ($this->defaultAtts as $key => $value) {
            $params["atts[{$key}]"] = $value;
        }

        return $pageUrl . '?' . http_build_query($params);
    }

    /**
     * Headers HTTP específicos para AJAX de Woodmart.
     * ⚠️ NO pedimos 'br' (Brotli) porque el cURL de Laragon en este
     * entorno no decodifica Brotli y rompe con "Unrecognized content
     * encoding type". El server entonces nos manda gzip/deflate.
     */
    protected function getHeaders(): array
    {
        return [
            'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) ' .
                'AppleWebKit/537.36 (KHTML, like Gecko) ' .
                'Chrome/120.0.0.0 Safari/537.36',
            'Accept'          => 'application/json, text/html, */*',
            'Accept-Language' => 'es-AR,es;q=0.9,en;q=0.8',
            'Accept-Encoding' => 'gzip, deflate',
            'X-Requested-With' => 'XMLHttpRequest',
            'Referer'         => $this->referer,
        ];
    }

    // ============================================================
    //  Parsing HTML (específico de Woodmart)
    // ============================================================

    /**
     * Parsea el HTML de la respuesta AJAX y extrae las cards de producto.
     */
    private function parseProductsFromHtml(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $wrapped = '<?xml encoding="UTF-8"><div>' . $html . '</div>';
        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//div[contains(@class, "wd-product")]');
        if ($nodes === false) {
            return [];
        }

        $products = [];
        foreach ($nodes as $node) {
            $parsed = $this->parseProductNode($xpath, $node);
            if ($parsed !== null) {
                $products[] = $parsed;
            }
        }

        return $products;
    }

    /**
     * Parsea un nodo <div class="wd-product" data-id="12345">.
     */
    protected function parseProductNode(DOMXPath $xpath, DOMNode $node): ?array
    {
        if (! $node instanceof DOMElement) {
            return null;
        }

        $rawExternalId = $node->getAttribute('data-id');
        if ($rawExternalId === '') {
            return null;
        }

        $externalId = $this->externalIdPrefix . $rawExternalId;

        // Nombre + source_url
        $name = null;
        $sourceUrl = null;
        $titleNode = $this->firstOf($xpath, './/h3[contains(@class, "wd-entities-title")]//a', $node);
        if ($titleNode instanceof DOMElement) {
            $name = $this->cleanText($titleNode->textContent);
            $sourceUrl = $this->resolveUrl($titleNode->getAttribute('href'));
        }

        if ($name === null || $name === '') {
            Log::warning("{$this->origin}Scraper: producto sin nombre, omitido", [
                'external_id' => $externalId,
            ]);
            return null;
        }

        $image    = $this->extractImage($xpath, $node);
        $gtmJson  = $this->extractGtmData($xpath, $node);
        $sku      = is_array($gtmJson) ? ($gtmJson['sku'] ?? null) : null;
        $stock    = $this->extractStock($xpath, $node, $gtmJson);
        $prices   = $this->extractPrices($xpath, $node, $gtmJson);
        $cats     = $this->extractCategories($xpath, $node);
        $brand    = $this->extractBrand($xpath, $node);

        return [
            'external_id'         => $externalId,
            'name'                => $name,
            'image'               => $image,
            'sku'                 => $sku,
            'stock'               => $stock,
            'list_price'          => $prices['list_price'],
            'cash_price'          => $prices['cash_price'],
            'price'               => $prices['cash_price'] ?? $prices['list_price'],
            'brand'               => $brand,
            'source_url'          => $sourceUrl,
            'categories_external' => $cats,
            'origin'              => $this->origin,
        ];
    }

    // ============================================================
    //  Hooks (las subclases pueden sobreescribir)
    // ============================================================

    /**
     * Imagen principal del card Woodmart.
     * Estrategia en orden de prioridad:
     *  1) data-image-url del slider de galería (imagen principal full-size)
     *  2) <img class="attachment-*"> dentro del product-thumb
     *  3) Cualquier <img> dentro del product-thumb como último fallback
     *
     * Si la URL tiene sufijo de tamaño WordPress (`-339x387.jpg`),
     * se lo quitamos para obtener la imagen full-size original.
     */
    protected function extractImage(DOMXPath $xpath, DOMNode $node): ?string
    {
        // 1) Slider de galería: data-image-url suele traer la imagen original full-size
        $slideNode = $this->firstOf(
            $xpath,
            './/div[contains(@class, "wd-product-grid-slide")][1]',
            $node
        );
        if ($slideNode instanceof DOMElement) {
            $rawImage = $slideNode->getAttribute('data-image-url');
            if ($rawImage !== '') {
                $image = $this->resolveUrl($rawImage);
                if ($image && preg_match('/\.(jpg|jpeg|png|webp|gif|avif)(\?|$)/i', $image)) {
                    return $this->stripWordpressSizeSuffix($image);
                }
            }
        }

        // 2) Cualquier <img.attachment-*> dentro del product-thumb
        $imgNodes = $xpath->query(
            './/div[contains(@class, "product-thumb")]//img[contains(@class, "attachment-")]',
            $node
        );
        if ($imgNodes !== false && $imgNodes->length > 0) {
            /** @var DOMElement $imgNode */
            $imgNode = $imgNodes->item(0);
            $rawImage = $imgNode->getAttribute('data-src')
                ?: $imgNode->getAttribute('data-lazy-src')
                ?: $imgNode->getAttribute('src');
            $image = $this->resolveUrl($rawImage);
            if ($image && preg_match('/\.(jpg|jpeg|png|webp|gif|avif)(\?|$)/i', $image)) {
                return $this->stripWordpressSizeSuffix($image);
            }
        }

        // 3) Último fallback: cualquier <img> dentro del product-thumb
        $anyImg = $this->firstOf(
            $xpath,
            './/div[contains(@class, "product-thumb")]//img',
            $node
        );
        if ($anyImg instanceof DOMElement) {
            $rawImage = $anyImg->getAttribute('data-src')
                ?: $anyImg->getAttribute('data-lazy-src')
                ?: $anyImg->getAttribute('src');
            $image = $this->resolveUrl($rawImage);
            if ($image && preg_match('/\.(jpg|jpeg|png|webp|gif|avif)(\?|$)/i', $image)) {
                return $this->stripWordpressSizeSuffix($image);
            }
        }

        return null;
    }

    /**
     * Quita el sufijo de tamaño que WordPress agrega a las imágenes.
     *   `WhatsApp-Image-2026-08-06-at-3.03.17-PM-339x387.jpeg`
     *     → `WhatsApp-Image-2026-08-06-at-3.03.17-PM.jpeg`
     */
    protected function stripWordpressSizeSuffix(string $url): string
    {
        return preg_replace('/-\d+x\d+(?=\.(jpe?g|png|webp|gif|avif)$)/i', '', $url) ?? $url;
    }

    /**
     * gtm4wp_productdata (JSON embebido). Contiene sku, price, stocklevel.
     */
    protected function extractGtmData(DOMXPath $xpath, DOMNode $node): ?array
    {
        $gtmNode = $this->firstOf($xpath, './/span[contains(@class, "gtm4wp_productdata")]', $node);
        if (! $gtmNode instanceof DOMElement) {
            return null;
        }

        $raw = $gtmNode->getAttribute('data-gtm4wp_product_data');
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode(html_entity_decode($raw), true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Stock del producto en la card del LISTADO. Prioridad:
     *  1) gtm4wp stocklevel
     *  2) input.qty[max] más alto (productos con variaciones tienen varios inputs;
     *     el primero suele ser el del producto base sin max)
     *  3) <p class="wd-product-stock">17 en stock</p> o "sin stock"
     *  4) Texto "N disponible" / "N en stock" en la card
     */
    protected function extractStock(DOMXPath $xpath, DOMNode $node, ?array $gtmJson): ?int
    {
        // 1) gtm4wp (validar que el valor sea numérico, no string vacío)
        if (is_array($gtmJson) && isset($gtmJson['stocklevel']) && $gtmJson['stocklevel'] !== '' && $gtmJson['stocklevel'] !== null) {
            return (int) $gtmJson['stocklevel'];
        }

        // 2) input.qty[max] — tomar el max más alto entre todos los inputs
        //    (el primero suele ser el del producto base sin max en productos con variaciones)
        $inputNodes = $xpath->query('.//input[contains(@class, "qty")]', $node);
        $maxValue   = 0;
        if ($inputNodes !== false) {
            foreach ($inputNodes as $input) {
                if (! $input instanceof DOMElement) {
                    continue;
                }
                $max = $input->getAttribute('max');
                if ($max !== '' && is_numeric($max) && (int) $max > $maxValue) {
                    $maxValue = (int) $max;
                }
            }
        }
        if ($maxValue > 0) {
            return $maxValue;
        }

        // 3) <p class="wd-product-stock">N en stock</p> o "sin stock"
        $stockNode = $this->firstOf($xpath, './/p[contains(@class, "wd-product-stock")]', $node);
        if ($stockNode instanceof DOMElement) {
            $text = $this->cleanText($stockNode->textContent);
            if ($text !== null) {
                if (preg_match('/(\d+)\s*(en stock|disponibles?|available|in stock)/i', $text, $matches)) {
                    return (int) $matches[1];
                }
                if (preg_match('/(sin stock|out of stock|agotado|sin disponibilidad)/i', $text)) {
                    return 0;
                }
            }
        }

        // 4) Texto "N disponible" en la card (caso Daz)
        $textContent = $this->cleanText($node->textContent);
        if ($textContent !== null) {
            if (preg_match('/\b(\d+)\s*disponibles?\b/i', $textContent, $m)
                || preg_match('/\b(\d+)\s*en\s*stock\b/i', $textContent, $m)) {
                return (int) $m[1];
            }
        }

        return null;
    }

    /**
     * Scrapea la página de DETALLE de un producto y devuelve el stock real.
     *
     * A diferencia de extractStock() (que parsea la card del LISTADO), este método
     * carga la URL individual del producto y extrae stock de la página completa,
     * que suele ser más preciso.
     *
     * Prioridad:
     *   1) gtm4wp_productdata (JSON embebido con stocklevel) — más confiable
     *   2) <p class="wd-product-stock">N en stock</p>      — texto humano
     *   3) Texto "sin stock" / "out of stock" / "agotado"  — stock=0
     *   4) input.qty[max] del producto principal            — último fallback
     *
     * NOTA sobre input.qty: en productos CON variaciones, Woodmart genera varios
     * inputs .qty (uno por cada variación, con su max=stock de esa variación).
     * El input "principal" (sin variación seleccionada) puede NO tener max.
     * Tomamos el max MÁS ALTO entre los inputs con max, asumiendo que es la
     * variante con más stock, que suele ser el producto por defecto.
     *
     * Implementado para Daz y Tuc (que extienden esta clase). Tero tiene su
     * propio fetchProductDetail() con lógica distinta (JSON-LD + HTML embebido).
     *
     * @return array{stock: int|null, stock_source: string|null}
     */
    public function fetchProductDetail(string $url): array
    {
        $body = $this->httpGet($url);
        [, $xpath] = $this->loadHtmlFragment($body);

        $stock       = null;
        $stockSource = null;

        // 1) gtm4wp_productdata (JSON embebido en <span class="gtm4wp_productdata">)
        $gtmNode = $this->firstOf($xpath, '//span[contains(@class, "gtm4wp_productdata")]');
        if ($gtmNode instanceof DOMElement) {
            $rawText = $this->cleanText($gtmNode->textContent);
            $json    = is_string($rawText) ? json_decode($rawText, true) : null;
            if (is_array($json) && isset($json['stocklevel'])
                && $json['stocklevel'] !== '' && $json['stocklevel'] !== null) {
                $stock       = (int) $json['stocklevel'];
                $stockSource = 'jsonld';
            }
        }

        // 2) <p class="wd-product-stock">N en stock</p> o "sin stock"
        if ($stock === null) {
            $stockNode = $this->firstOf($xpath, '//p[contains(@class, "wd-product-stock")]');
            if ($stockNode instanceof DOMElement) {
                $text = $this->cleanText($stockNode->textContent);
                if ($text !== null) {
                    if (preg_match('/(\d+)\s*(en stock|disponibles?|available|in stock)/i', $text, $m)) {
                        $stock       = (int) $m[1];
                        $stockSource = 'text';
                    } elseif (preg_match('/(sin stock|out of stock|agotado|sin disponibilidad)/i', $text)) {
                        $stock       = 0;
                        $stockSource = 'text';
                    }
                }
            }
        }

        // 3) Buscar "N disponible" o "N en stock" en el body (caso Daz: "1 disponible"
        //    en texto libre, sin la clase estándar). También "sin stock" general.
        if ($stock === null) {
            if (preg_match('/\b(\d+)\s*disponibles?\b/i', $body, $m)
                || preg_match('/\b(\d+)\s*en\s*stock\b/i', $body, $m)) {
                $stock       = (int) $m[1];
                $stockSource = 'text';
            } elseif (preg_match('/\b(sin stock|out of stock|agotado|sin disponibilidad)\b/i', $body)) {
                $stock       = 0;
                $stockSource = 'text';
            }
        }

        // 4) input.qty[max] (último recurso — ver nota en docblock sobre variaciones)
        if ($stock === null) {
            $inputNodes = $xpath->query('//input[contains(@class, "qty")]');
            $maxValue   = 0;
            if ($inputNodes !== false) {
                foreach ($inputNodes as $input) {
                    if (! $input instanceof DOMElement) {
                        continue;
                    }
                    $max = $input->getAttribute('max');
                    if ($max !== '' && is_numeric($max) && (int) $max > $maxValue) {
                        $maxValue = (int) $max;
                    }
                }
            }
            if ($maxValue > 0) {
                $stock       = $maxValue;
                $stockSource = 'input_max';
            }
        }

        return [
            'stock'        => $stock,
            'stock_source' => $stockSource,
        ];
    }

    /**
     * Precios (lista + efectivo). 1° nodo = lista, 2° = efectivo.
     * Si solo hay uno, ese es el efectivo.
     */
    protected function extractPrices(DOMXPath $xpath, DOMNode $node, ?array $gtmJson): array
    {
        $listPrice = null;
        $cashPrice = null;

        $priceNodes = $xpath->query('.//span[contains(@class, "woocommerce-Price-amount")]', $node);
        if ($priceNodes !== false && $priceNodes->length > 0) {
            $listPrice = $this->parsePriceAmount($priceNodes->item(0)->textContent);
            if ($priceNodes->length > 1) {
                $cashPrice = $this->parsePriceAmount($priceNodes->item(1)->textContent);
            } else {
                // Si solo hay un precio, ese es el efectivo (no hay lista)
                $cashPrice = $listPrice;
                $listPrice = null;
            }
        }

        // Si hay gtm4wp, sobrescribimos el precio efectivo (más confiable)
        $gtmPrice = is_array($gtmJson) && isset($gtmJson['price'])
            ? (float) $gtmJson['price']
            : null;

        if ($gtmPrice !== null) {
            $cashPrice = $cashPrice ?? $gtmPrice;
            if ($listPrice === null) {
                $listPrice = $cashPrice;
            }
        }

        return [
            'list_price' => $listPrice,
            'cash_price' => $cashPrice,
        ];
    }

    /**
     * Categorías del producto. Default: extrae de <div class="wd-product-cats"><a>.
     * Tuc las guarda en el class attribute del root (override en TucScraperService).
     */
    protected function extractCategories(DOMXPath $xpath, DOMNode $node): array
    {
        $categoryNodes = $xpath->query('.//div[contains(@class, "wd-product-cats")]//a', $node);
        $categories = [];
        if ($categoryNodes !== false) {
            foreach ($categoryNodes as $catNode) {
                $catName = $this->cleanText($catNode->textContent);
                if ($catName !== null && $catName !== '') {
                    $categories[] = $catName;
                }
            }
        }
        return $categories;
    }

    /**
     * Marca del producto. Default: extrae de <div class="wd-product-brands-links"><a>.
     * Tuc no muestra marca (override devuelve null).
     */
    protected function extractBrand(DOMXPath $xpath, DOMNode $node): ?string
    {
        $brandNode = $this->firstOf($xpath, './/div[contains(@class, "wd-product-brands-links")]//a', $node);
        if ($brandNode instanceof DOMElement) {
            $brand = $this->cleanText($brandNode->textContent);
            return $brand !== '' ? $brand : null;
        }
        return null;
    }
}

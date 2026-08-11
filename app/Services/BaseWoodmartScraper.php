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
 */
abstract class BaseWoodmartScraper
{
    protected string $baseUrl = '';
    protected string $externalIdPrefix = '';
    protected string $origin = 'manual';
    protected string $referer = '';
    protected string $shopPath = '/productos/';


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

    /**
     * Cada subclase configura aquí su sitio (baseUrl, origin, referer, etc.).
     */
    abstract protected function configure(): void;

    public function __construct()
    {
        $this->configure();
    }

    // ============================================================
    //  Pipeline principal
    // ============================================================

    /**
     * Scrapea todas las páginas (o hasta $maxPages) y devuelve
     * los productos normalizados + estadísticas.
     *
     * @param  ?\Closure $onProgress  function(array $product, int $page, int $total, ?int $estimatedTotal): void
     * @return array{
     *   stats: array<string,int>,
     *   products: array<int, array<string,mixed>>,
     *   errors: array<int,string>,
     *   estimated_total: ?int
     * }
     */
    public function scrape(?int $maxPages = null, int $delaySeconds = 1, ?\Closure $onProgress = null): array
    {
        $stats = [
            'pages' => 0,
            'products_total' => 0,
            'products_with_image' => 0,
            'products_with_brand' => 0,
            'products_without_price' => 0,
        ];
        $errors = [];
        $allProducts = [];
        $estimatedTotal = null;

        $page = 1;
        $url = $this->baseUrl . $this->shopPath;


        do {
            try {
                $result = $this->fetchPage($url);
                $stats['pages']++;

                // Capturar el total estimado de la primera página (viene en resultCount)
                if ($estimatedTotal === null && isset($result['total'])) {
                    $estimatedTotal = $result['total'];
                }

                foreach ($result['products'] as $product) {
                    $allProducts[] = $product;
                    $stats['products_total']++;
                    if (! empty($product['image'])) {
                        $stats['products_with_image']++;
                    }
                    if (! empty($product['brand'])) {
                        $stats['products_with_brand']++;
                    }
                    if ($product['list_price'] === null && $product['cash_price'] === null) {
                        $stats['products_without_price']++;
                    }

                    if ($onProgress !== null) {
                        $onProgress($product, $page, $stats['products_total'], $estimatedTotal);
                    }
                }

                Log::info("{$this->origin}Scraper: página procesada", [
                    'page'  => $page,
                    'url'   => $url,
                    'count' => count($result['products']),
                    'total' => $estimatedTotal,
                    'next'  => $result['nextPage'] ?? null,
                ]);
            } catch (\Throwable $e) {
                $errors[] = "Página {$page}: " . $e->getMessage();
                Log::error("{$this->origin}Scraper: error en página", [
                    'page'  => $page,
                    'url'   => $url,
                    'error' => $e->getMessage(),
                ]);
                // Salimos si la primera página falla — algo está muy mal
                if ($page === 1) {
                    break;
                }
            }

            if ($maxPages !== null && $page >= $maxPages) {
                break;
            }

            $url = $result['nextPage'] ?? null;
            $page++;

            if ($url && $delaySeconds > 0) {
                sleep($delaySeconds);
            }
        } while ($url);

        return [
            'stats' => $stats,
            'products' => $allProducts,
            'errors' => $errors,
            'estimated_total' => $estimatedTotal,
        ];
    }

    /**
     * Descarga UNA página y parsea sus productos.
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
     * Ej: "Mostrando 1–18 de 1500 resultados" → 1500
     *     "Mostrando 19-36 de 1500 resultados" → 1500
     *
     * Devuelve null si no puede parsearlo (modo degrada a barra "indeterminada").
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
            // Si ya es número, listo
            if (is_numeric($raw)) {
                return (int) $raw;
            }
            // Si es string tipo "Mostrando X-Y de Z resultados"
            if (is_string($raw) && preg_match('/de\s+(\d+)\s+result/i', $raw, $m)) {
                return (int) $m[1];
            }
            // Variante: "Z productos"
            if (is_string($raw) && preg_match('/(\d+)\s+productos?/i', $raw, $m)) {
                return (int) $m[1];
            }
        }

        return null;
    }

    // ============================================================
    //  HTTP
    // ============================================================

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

    private function getHeaders(): array
    {
        return [
            'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) ' .
                'AppleWebKit/537.36 (KHTML, like Gecko) ' .
                'Chrome/120.0.0.0 Safari/537.36',
            'Accept'          => 'application/json, text/html, */*',
            'Accept-Language' => 'es-AR,es;q=0.9,en;q=0.8',
            'Accept-Encoding' => 'gzip, deflate, br',
            'X-Requested-With' => 'XMLHttpRequest',
            'Referer'         => $this->referer,
        ];
    }

    // ============================================================
    //  Parsing HTML
    // ============================================================

    private function parseProductsFromHtml(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        // Envoltura mínima para que DOMDocument acepte fragmento suelto
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
     * Coordina todos los hooks de extracción.
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
     * Imagen principal del card. Estrategia en orden de prioridad:
     *  1) data-image-url del slider de galería (imagen principal full-size)
     *  2) <img class="attachment-*"> dentro del product-thumb
     *     (cubre attachment-large, attachment-339x387, attachment-300x300, etc.)
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
        //    (attachment-large, attachment-339x387, attachment-300x300, etc.)
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
     * Quita el sufijo de tamaño que WordPress agrega a las imágenes:
     *   `WhatsApp-Image-2026-08-06-at-3.03.17-PM-339x387.jpeg`
     *     → `WhatsApp-Image-2026-08-06-at-3.03.17-PM.jpeg`
     *
     * Solo si el sufijo es exactamente del patrón `-NNNxNNN` antes de la
     * extensión. Si no matchea, devuelve la URL tal cual.
     */
    protected function stripWordpressSizeSuffix(string $url): string
    {
        return preg_replace('/-\d+x\d+(?=\.(jpe?g|png|webp|gif|avif)$)/i', '', $url) ?? $url;
    }

    /**
     * gtm4wp_productdata (JSON embebido). Contiene sku, price, stocklevel.
     * Devuelve null si no está presente o no se pudo parsear.
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
     * Stock del producto. Prioridad:
     *  1) gtm4wp stocklevel
     *  2) input.qty[max]
     *  3) <p class="wd-product-stock">17 en stock</p>
     */
    protected function extractStock(DOMXPath $xpath, DOMNode $node, ?array $gtmJson): ?int
    {
        // 1) gtm4wp
        if (is_array($gtmJson) && isset($gtmJson['stocklevel'])) {
            return (int) $gtmJson['stocklevel'];
        }

        // 2) input.qty[max]
        $inputNode = $this->firstOf($xpath, './/input[contains(@class, "qty")]', $node);
        if ($inputNode instanceof DOMElement) {
            $max = $inputNode->getAttribute('max');
            if ($max !== '' && is_numeric($max)) {
                return (int) $max;
            }
        }

        // 3) Texto "X en stock" / "sin stock"
        $stockNode = $this->firstOf($xpath, './/p[contains(@class, "wd-product-stock")]', $node);
        if ($stockNode instanceof DOMElement) {
            $text = $this->cleanText($stockNode->textContent);
            if (preg_match('/(\d+)\s*(en stock|disponibles?|available|in stock)/i', $text, $matches)) {
                return (int) $matches[1];
            }
            if (preg_match('/(sin stock|out of stock|agotado|sin disponibilidad)/i', $text)) {
                return 0;
            }
        }

        return null;
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
            $listPrice = $this->extractPriceAmount($priceNodes->item(0)->textContent);
            if ($priceNodes->length > 1) {
                $cashPrice = $this->extractPriceAmount($priceNodes->item(1)->textContent);
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
                if ($catName !== '') {
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
            return $this->cleanText($brandNode->textContent) ?: null;
        }
        return null;
    }

    // ============================================================
    //  Helpers
    // ============================================================

    /**
     * Primer nodo que matchea el query (opcionalmente scoped a un contexto).
     */
    protected function firstOf(DOMXPath $xpath, string $query, ?DOMNode $context = null)
    {
        $result = $context
            ? $xpath->query($query, $context)
            : $xpath->query($query);

        if ($result === false || $result->length === 0) {
            return null;
        }
        return $result->item(0);
    }

    /**
     * Normaliza una URL: si es relativa la completa con baseUrl.
     */
    protected function resolveUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        if (str_starts_with($url, '/')) {
            return $this->baseUrl . $url;
        }

        return $this->baseUrl . '/' . $url;
    }

    /**
     * Extrae un número de un string con formato AR o US.
     * Detecta automáticamente:
     *  - "$ 4.200"      → 4200    (AR: miles con punto, sin decimales)
     *  - "$ 4.200,50"   → 4200.5  (AR: miles con punto, decimal con coma)
     *  - "$20,500.00"   → 20500   (US: miles con coma, decimal con punto)
     *  - "$ 1.234.567"  → 1234567 (AR: solo puntos, varios)
     *  - "$ 1,234,567"  → 1234567 (US: solo comas, varias)
     *  - "$4.7"         → 4.7     (decimal, sin ambigüedad)
     *
     * Heurística: si están ambos, el de la DERECHA es el decimal.
     */
    protected function extractPriceAmount(string $text): ?float
    {
        $cleaned = preg_replace('/[^\d.,\-]/', '', $text);
        if ($cleaned === null || $cleaned === '') {
            return null;
        }

        $hasComma = str_contains($cleaned, ',');
        $hasDot = str_contains($cleaned, '.');

        if ($hasComma && $hasDot) {
            $lastComma = strrpos($cleaned, ',');
            $lastDot = strrpos($cleaned, '.');
            if ($lastComma > $lastDot) {
                // AR format: 1.234,56
                $cleaned = str_replace('.', '', $cleaned);
                $cleaned = str_replace(',', '.', $cleaned);
            } else {
                // US format: 1,234.56
                $cleaned = str_replace(',', '', $cleaned);
            }
        } elseif ($hasComma) {
            $parts = explode(',', $cleaned);
            if (count($parts) > 2 || strlen(end($parts)) === 3) {
                $cleaned = str_replace(',', '', $cleaned);
            } else {
                $cleaned = str_replace(',', '.', $cleaned);
            }
        } elseif ($hasDot) {
            $parts = explode('.', $cleaned);
            if (count($parts) > 2 || strlen(end($parts)) === 3) {
                $cleaned = str_replace('.', '', $cleaned);
            }
        }

        if (! is_numeric($cleaned)) {
            return null;
        }

        return (float) $cleaned;
    }

    /**
     * Limpia espacios y saltos de línea.
     */
    protected function cleanText(?string $text): string
    {
        if ($text === null) {
            return '';
        }
        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }
}

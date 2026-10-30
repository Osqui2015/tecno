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
 * Base genérica para scrapers de e-commerce que devuelven HTML
 * (a diferencia de BaseWoodmartScraper que consume AJAX/JSON de WooCommerce).
 *
 * Pensada para sitios como Tiendanube/Nuvemshop (Teromatero, etc.) donde
 * cada página es HTML completo del servidor y la paginación es
 * tradicional con `/page/N/`.
 *
 * Las subclases solo implementan los hooks:
 *  - fetchPage(string $url): array  → trae una página y devuelve ['products' => [...], 'nextPage' => ?string, 'total' => ?int]
 *  - parseProductNode(DOMXPath, DOMNode): ?array  → parsea UNA card
 *  - extractImage(), extractPrices(), extractStock(), etc. (opcionales, tienen default razonable)
 */
abstract class AbstractProductScraper
{
    protected string $baseUrl = '';
    protected string $origin = 'manual';
    protected string $referer = '';
    protected string $shopPath = '/';


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

                if ($estimatedTotal === null && isset($result['total']) && $result['total'] !== null) {
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
                    if (($product['list_price'] ?? null) === null && ($product['cash_price'] ?? null) === null) {
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
                if ($page === 1) {
                    // Si la primera página falla, no seguimos — algo está muy mal
                    break;
                }
                // Si falló una página intermedia, intentamos saltar a la siguiente
                $page++;
                $url = $this->buildNextPageUrl($page);
                if ($url && $delaySeconds > 0) {
                    sleep($delaySeconds);
                }
                continue;
            }

            if ($maxPages !== null && $page >= $maxPages) {
                break;
            }

            // ⚠️ Importante: usar el nextPage que devolvió fetchPage().
            // Si fetchPage devolvió nextPage=null (porque la página estaba
            // vacía o el sitio no tiene más páginas), el loop se corta.
            // NO hacemos fallback a buildNextPageUrl porque eso generaría
            // un loop infinito scrapeando páginas vacías.
            $page++;
            $url = $result['nextPage'] ?? null;

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
     * Construye la URL de la página N. Las subclases pueden sobreescribir
     * si el sitio usa otro patrón (ej. ?page=N en vez de /page/N/).
     *
     * ⚠️ Este método se usa SOLO como fallback cuando fetchPage()
     * devuelve nextPage=null pero el loop aún no terminó (caso raro,
     * normalmente significa que se necesita re-construir la URL).
     */
    protected function buildNextPageUrl(int $page): ?string
    {
        return rtrim($this->baseUrl, '/') . $this->shopPath . 'page/' . $page . '/';
    }

    // ============================================================
    //  HTTP helpers
    // ============================================================

    /**
     * Hace GET a una URL con headers "humanos" y devuelve el body.
     * Las subclases lo usan en fetchPage().
     */
    protected function httpGet(string $url, int $timeout = 30): string
    {
        $response = Http::withHeaders($this->getHeaders())
            ->withOptions([
                'allow_redirects' => true,
                'timeout' => $timeout,
                'connect_timeout' => 10,
            ])
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("HTTP {$response->status()} al solicitar {$url}");
        }

        return $response->body();
    }

    protected function getHeaders(): array
    {
        return [
            'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) ' .
                'AppleWebKit/537.36 (KHTML, like Gecko) ' .
                'Chrome/120.0.0.0 Safari/537.36',
            'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language' => 'es-AR,es;q=0.9,en;q=0.8',
            // OJO: NO pedimos `br` (Brotli) porque el cURL de Laragon
            // en este entorno no lo decodifica y rompe con "Unrecognized
            // content encoding type". El server entonces nos manda gzip
            // o deflate, que sí entendemos.
            'Accept-Encoding' => 'gzip, deflate',
            'Referer'         => $this->referer ?: $this->baseUrl . '/',
        ];
    }

    // ============================================================
    //  Parsing HTML helpers
    // ============================================================

    /**
     * Carga un fragmento HTML suelto en un DOMDocument y devuelve XPath.
     * Envuelve el fragmento en un <div> para que DOMDocument lo acepte
     * aunque sea un nodo raíz huérfano.
     */
    protected function loadHtmlFragment(string $html): array
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $wrapped = '<?xml encoding="UTF-8"><div>' . $html . '</div>';
        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($dom);

        return [$dom, $xpath];
    }

    /**
     * Devuelve el primer nodo que matchea el query, o null.
     */
    protected function firstOf(DOMXPath $xpath, string $query, ?DOMNode $context = null): ?DOMNode
    {
        $nodes = $context
            ? $xpath->query($query, $context)
            : $xpath->query($query);
        if ($nodes === false || $nodes->length === 0) {
            return null;
        }
        return $nodes->item(0);
    }

    /**
     * Limpia whitespace de un textContent.
     */
    protected function cleanText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        return $text === '' ? null : $text;
    }

    /**
     * Resuelve una URL relativa a absoluta usando baseUrl.
     */
    protected function resolveUrl(string $href): ?string
    {
        $href = trim($href);
        if ($href === '') {
            return null;
        }
        if (str_starts_with($href, '//')) {
            return 'https:' . $href;
        }
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        if (str_starts_with($href, '/')) {
            return rtrim($this->baseUrl, '/') . $href;
        }
        return rtrim($this->baseUrl, '/') . '/' . $href;
    }

    /**
     * Asegura que la URL de Tiendanube tenga un sufijo de tamaño válido.
     *
     * IMPORTANTE: el CDN de Tiendanube (dcdn-us.mitiendanube.com) ahora devuelve
     * "Access Denied" para URLs sin sufijo de tamaño (requieren autenticación
     * por referer o token). Las URLs con sufijo tipo `-1024-1024.webp` son
     * públicas y funcionan sin auth.
     *
     * Comportamiento:
     *  - Si la URL ya tiene un sufijo `-W-H` antes de la extensión: la deja
     *  - Si la URL no tiene sufijo: agrega `-1024-1024` antes de la extensión
     *
     * Ej:
     *  - `imagen.webp`              → `imagen-1024-1024.webp`
     *  - `imagen-640-0-abc.webp`    → `imagen-640-0-abc.webp` (sin cambio)
     *  - `imagen.webp?v=1`          → `imagen-1024-1024.webp?v=1`
     */
    protected function ensureTiendaNubeSizeSuffix(string $url, string $size = '1024-1024'): string
    {
        // Patrón: nombre-W-H-HASH.ext (con W y H numéricos, antes de la extensión)
        // Si la URL ya tiene un sufijo tipo -640-0, -480-0, -1024-1024, etc. → no tocar
        if (preg_match('/-\d+-\d+(?=\.[a-z]{2,4}(?:\?|$))/i', $url)) {
            return $url;
        }

        // Si no tiene sufijo, agregar "-{size}" antes de la extensión
        // Casos: "imagen.webp", "imagen.webp?v=1", "imagen.jpg"
        if (preg_match('/^(.+?)(\.[a-z]{2,4})(\?.*)?$/i', $url, $m)) {
            $base     = $m[1];
            $ext      = $m[2];
            $query    = $m[3] ?? '';
            return $base . '-' . $size . $ext . $query;
        }

        // Si no parece una URL de imagen, devolver sin tocar
        return $url;
    }

    /**
     * DEPRECATED: mantener por compatibilidad con código viejo.
     * Usar ensureTiendaNubeSizeSuffix() en su lugar.
     */
    protected function stripTiendaNubeSizeSuffix(string $url): string
    {
        return $this->ensureTiendaNubeSizeSuffix($url, '1024-1024');
    }

    /**
     * Parsea un monto en formato argentino "$ 4.200,50" o US "$20,500.00"
     * a float. Detecta automáticamente el formato.
     *
     *  - "$ 4.200"      → 4200    (AR: miles con punto, sin decimales)
     *  - "$ 4.200,50"   → 4200.5  (AR: miles con punto, decimal con coma)
     *  - "$20,500.00"   → 20500   (US: miles con coma, decimal con punto)
     *  - "$ 1.234.567"  → 1234567 (AR: solo puntos, varios)
     *  - "$ 1,234,567"  → 1234567 (US: solo comas, varias)
     *
     * Heurística: si están ambos, el de la DERECHA es el decimal.
     */
    protected function parsePriceAmount(string $raw): ?float
    {
        $cleaned = preg_replace('/[^\d.,\-]/', '', $raw);
        if ($cleaned === null || $cleaned === '') {
            return null;
        }

        $hasComma = str_contains($cleaned, ',');
        $hasDot = str_contains($cleaned, '.');

        if ($hasComma && $hasDot) {
            $lastComma = strrpos($cleaned, ',');
            $lastDot = strrpos($cleaned, '.');
            if ($lastComma > $lastDot) {
                // AR: 1.234,56
                $cleaned = str_replace('.', '', $cleaned);
                $cleaned = str_replace(',', '.', $cleaned);
            } else {
                // US: 1,234.56
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

    // ============================================================
    //  Hooks (las subclases implementan)
    // ============================================================

    /**
     * Trae una página y devuelve ['products' => [...], 'nextPage' => ?string, 'total' => ?int].
     * @return array{products: array<int, array<string,mixed>>, nextPage: ?string, total: ?int}
     */
    abstract public function fetchPage(string $url): array;

    /**
     * Parsea UNA card de producto y devuelve el array normalizado o null si no se pudo.
     *
     * @return array<string,mixed>|null
     */
    abstract protected function parseProductNode(DOMXPath $xpath, DOMNode $node): ?array;
}

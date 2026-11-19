<?php

namespace App\Services;

use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use RuntimeException;

/**
 * Scraper de productos desde tustecnologiastuc.com
 *
 * Utiliza el endpoint real de Woodmart vía POST:
 * POST https://www.tustecnologiastuc.com/wp-admin/admin-ajax.php
 * Content-Type: application/x-www-form-urlencoded
 * action=woodmart_get_products_shortcode
 * woo_ajax=1
 * paged={pagina}
 * atts[...]
 *
 * MODOS DE EJECUCIÓN (DRIVERS):
 *  1) Playwright (Recomendado): Abre Chromium real, supera el challenge
 *     de Cloudflare automáticamente y ejecuta la petición AJAX con las
 *     cookies de sesión legítimas generadas por el navegador.
 *  2) HTTP Directo (con Cookie): Envía la petición con cURL de Laravel
 *     usando la cookie configurada en .env (TUC_CF_COOKIE="cf_clearance=...").
 */
class TucScraperService extends BaseWoodmartScraper
{
    public const ITEMS_PER_PAGE = 70;
    public const ESTIMATED_TOTAL = 2226;

    protected function configure(): void
    {
        $this->baseUrl  = 'https://www.tustecnologiastuc.com';
        $this->origin   = 'tuc';
        $this->referer  = 'https://www.tustecnologiastuc.com/';
        $this->shopPath = '/tienda/';
    }

    /**
     * Resuelve el número de página a partir de un entero, número o URL.
     */
    public function resolvePageNumber(string|int $urlOrPage): int
    {
        if (is_numeric($urlOrPage)) {
            return max(1, (int) $urlOrPage);
        }

        if (preg_match('/[?&]paged=(\d+)/i', $urlOrPage, $m)) {
            return max(1, (int) $m[1]);
        }

        if (preg_match('/\/page\/(\d+)/i', $urlOrPage, $m)) {
            return max(1, (int) $m[1]);
        }

        return 1;
    }

    /**
     * Construye la URL/identificador de la página N para el loop de fallback.
     */
    protected function buildNextPageUrl(int $page): ?string
    {
        return (string) $page;
    }

    /**
     * Determina el driver a utilizar: 'playwright' o 'http'.
     */
    public function resolveDriver(): string
    {
        $configured = env('TUC_SCRAPER_DRIVER');
        if (! empty($configured)) {
            return strtolower($configured);
        }

        // En tests unitarios, usar driver HTTP para aprovechar Http::fake()
        if (app()->runningUnitTests()) {
            return 'http';
        }

        // Si hay cookie externa configurada explícitamente y no se forzó playwright, usar http
        if (! empty(env('TUC_CF_COOKIE'))) {
            return 'http';
        }

        // Por defecto: Playwright si el script existe
        $scriptPath = base_path('scripts/tuc-playwright-fetch.js');
        if (file_exists($scriptPath)) {
            return 'playwright';
        }

        return 'http';
    }

    /**
     * Descarga UNA página (vía Playwright o HTTP con cookies) y parsea sus productos.
     *
     * @param string $url
     * @return array{products: array<int, array<string,mixed>>, nextPage: ?string, total: ?int, page: int, status: ?string, driver: string, http_code: int}
     */
    public function fetchPage(string $url): array
    {
        $page = $this->resolvePageNumber($url);
        $driver = $this->resolveDriver();

        if ($driver === 'playwright') {
            try {
                return $this->fetchPageViaPlaywright($page);
            } catch (\Throwable $e) {
                // Si Playwright falla (ej: falta de dependencias en hosting compartido),
                // intentamos fallback a HTTP si hay cookie configurada o avisamos claramente.
                Log::warning("TucScraper: Falló Playwright ({$e->getMessage()}). Probando driver HTTP de respaldo...");
                if (! empty(env('TUC_CF_COOKIE'))) {
                    return $this->fetchPageViaHttp($page);
                }
                throw $e;
            }
        }

        return $this->fetchPageViaHttp($page);
    }

    /**
     * Ejecuta la petición utilizando Playwright Chromium real.
     */
    public function fetchPageViaPlaywright(int $page): array
    {
        $scriptPath = base_path('scripts/tuc-playwright-fetch.js');
        if (! file_exists($scriptPath)) {
            throw new RuntimeException("No se encontró el script de Playwright en: {$scriptPath}");
        }

        $command = ['node', $scriptPath, "--page={$page}", "--items-per-page=" . self::ITEMS_PER_PAGE];

        $proxy = config("services.scraper.{$this->origin}_proxy")
            ?: config('services.scraper.proxy')
            ?: env(strtoupper($this->origin) . '_SCRAPER_PROXY')
            ?: env('SCRAPER_PROXY');

        if (! empty($proxy)) {
            $command[] = "--proxy={$proxy}";
        }

        $process = new Process($command, base_path(), null, null, 60);
        $process->run();

        if (! $process->isSuccessful()) {
            $errorOutput = trim($process->getErrorOutput());
            $jsonError = json_decode($errorOutput, true);
            $msg = is_array($jsonError) && isset($jsonError['error'])
                ? $jsonError['error']
                : ($errorOutput ?: $process->getOutput());

            throw new RuntimeException("Fallo en Playwright (página {$page}): {$msg}");
        }

        $output = trim($process->getOutput());
        $payload = json_decode($output, true);

        if (! is_array($payload) || ! isset($payload['data']['items'])) {
            throw new RuntimeException("Respuesta inválida de Playwright para página {$page}. Salida: " . substr($output, 0, 200));
        }

        $html = (string) ($payload['data']['items'] ?? '');
        $status = $payload['data']['status'] ?? null;
        $httpCode = (int) ($payload['status'] ?? 200);

        $products = $this->parseProductsFromHtml($html);
        $hasMore = ($status !== 'no-more-posts') && (count($products) > 0);
        $nextPage = $hasMore ? (string) ($page + 1) : null;

        return [
            'products'  => $products,
            'nextPage'  => $nextPage,
            'total'     => self::ESTIMATED_TOTAL,
            'page'      => $page,
            'status'    => $status,
            'driver'    => 'playwright',
            'http_code' => $httpCode,
        ];
    }

    /**
     * Ejecuta la petición utilizando HTTP directo de Laravel con cookies externas.
     */
    public function fetchPageViaHttp(int $page): array
    {
        $endpoint = rtrim($this->baseUrl, '/') . '/wp-admin/admin-ajax.php';

        $payload = [
            'action'   => 'woodmart_get_products_shortcode',
            'woo_ajax' => 1,
            'paged'    => $page,
            'atts'     => [
                'orderby'                  => 'date',
                'pagination'               => 'more-btn',
                'items_per_page'           => self::ITEMS_PER_PAGE,
                'elementor'                => 'true',
                'columns'                  => ['size' => 5],
                'columns_tablet'           => ['size' => 3],
                'columns_mobile'           => ['size' => 2],
                'img_size'                 => 'custom',
                'img_size_custom'          => ['width' => 339, 'height' => 387],
                'products_with_background' => 0,
                'products_shadow'          => 0,
                'force_not_ajax'           => 'no',
            ],
        ];

        $userAgent = env('TUC_USER_AGENT')
            ?: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

        $headers = [
            'X-Requested-With' => 'XMLHttpRequest',
            'Referer'          => $this->referer ?: ($this->baseUrl . '/'),
            'Origin'           => rtrim($this->baseUrl, '/'),
            'User-Agent'       => $userAgent,
            'Accept'           => 'application/json, text/javascript, */*',
        ];

        $cfCookie = env('TUC_CF_COOKIE');
        if (! empty($cfCookie)) {
            $headers['Cookie'] = $cfCookie;
        }

        $response = Http::asForm()
            ->withHeaders($headers)
            ->withOptions($this->getHttpOptions(30))
            ->post($endpoint, $payload);

        if (! $response->successful()) {
            $snippet = trim(preg_replace('/\s+/', ' ', strip_tags($response->body())));
            $snippet = mb_substr($snippet, 0, 150);
            $extra = $snippet !== '' ? " — Detalle: {$snippet}" : '';

            $hint = '';
            if ($response->status() === 403) {
                $hint = "\n💡 Cloudflare bloqueó la petición (403 Forbidden). "
                    . "\n   Opciones de solución:"
                    . "\n   1) Usar Playwright Chromium (TUC_SCRAPER_DRIVER=playwright)"
                    . "\n   2) O configurar cookie en .env: TUC_CF_COOKIE=\"cf_clearance=...\"";
            }

            throw new RuntimeException(
                "HTTP {$response->status()} al solicitar {$endpoint} (página {$page}){$extra}{$hint}"
            );
        }

        $body = $response->body();
        $data = json_decode($body, true);

        if (! is_array($data) || ! isset($data['items'])) {
            throw new RuntimeException(
                'Respuesta no es JSON válido de admin-ajax.php o no tiene "items". Primeros 200 chars: ' . substr($body, 0, 200)
            );
        }

        $html = (string) ($data['items'] ?? '');
        $status = $data['status'] ?? null;

        $products = $this->parseProductsFromHtml($html);
        $hasMore = ($status !== 'no-more-posts') && (count($products) > 0);
        $nextPage = $hasMore ? (string) ($page + 1) : null;

        return [
            'products'  => $products,
            'nextPage'  => $nextPage,
            'total'     => self::ESTIMATED_TOTAL,
            'page'      => $page,
            'status'    => $status,
            'driver'    => 'http',
            'http_code' => $response->status(),
        ];
    }

    /**
     * Tuc guarda las categorías en el atributo `class` del root como
     * tokens `product_cat-{slug}`. Extraemos todos y los convertimos a
     * nombres legibles ("hogar" → "Hogar").
     */
    protected function extractCategories(DOMXPath $xpath, DOMNode $node): array
    {
        if (! $node instanceof DOMElement) {
            return [];
        }

        $class = $node->getAttribute('class');
        if ($class === '') {
            return [];
        }

        if (! preg_match_all('/\bproduct_cat-([a-z0-9\-]+)/i', $class, $matches)) {
            return [];
        }

        // Convertir slug → nombre legible y deduplicar
        return array_values(array_unique(array_map(
            fn (string $slug) => ucwords(str_replace('-', ' ', $slug)),
            $matches[1]
        )));
    }

    /**
     * Tuc no muestra marca en la card del producto.
     */
    protected function extractBrand(DOMXPath $xpath, DOMNode $node): ?string
    {
        return null;
    }
}

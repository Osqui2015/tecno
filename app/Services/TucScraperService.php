<?php

namespace App\Services;

use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Scraper de productos desde tustecnologiastuc.com
 *
 * Utiliza el endpoint real de Woodmart vía POST:
 * POST https://www.tustecnologiastuc.com/wp-admin/admin-ajax.php
 * Content-Type: application/x-www-form-urlencoded
 *
 * action=woodmart_get_products_shortcode
 * woo_ajax=1
 * paged={pagina}
 * atts[...]
 *
 * Paginación con 70 productos por página (aprox 32 páginas para ~2226 productos).
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
     * Descarga UNA página enviando un POST a admin-ajax.php y parsea sus productos.
     *
     * @param string $url
     * @return array{products: array<int, array<string,mixed>>, nextPage: ?string, total: ?int, page: int, status: ?string}
     */
    public function fetchPage(string $url): array
    {
        $page = $this->resolvePageNumber($url);
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

        $headers = [
            'X-Requested-With' => 'XMLHttpRequest',
            'Referer'          => $this->referer ?: ($this->baseUrl . '/'),
            'User-Agent'       => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Accept'           => 'application/json, text/javascript, */*',
        ];

        $response = Http::asForm()
            ->withHeaders($headers)
            ->withOptions($this->getHttpOptions(30))
            ->post($endpoint, $payload);

        if (! $response->successful()) {
            $snippet = trim(preg_replace('/\s+/', ' ', strip_tags($response->body())));
            $snippet = mb_substr($snippet, 0, 150);
            $extra = $snippet !== '' ? " — Detalle: {$snippet}" : '';
            throw new RuntimeException(
                "HTTP {$response->status()} al solicitar {$endpoint} (página {$page}){$extra}"
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
            'products' => $products,
            'nextPage' => $nextPage,
            'total'    => self::ESTIMATED_TOTAL,
            'page'     => $page,
            'status'   => $status,
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

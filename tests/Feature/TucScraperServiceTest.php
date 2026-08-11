<?php

namespace Tests\Feature;

use App\Services\TucScraperService;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TucScraperServiceTest extends TestCase
{
    /**
     * HTML mínimo de un producto de tustecnologiastuc.com, basado en el
     * response real del endpoint admin-ajax.php.
     */
    private const TUC_PRODUCT_HTML = <<<'HTML'
<div class="wd-product wd-col wd-hover-fw-button wd-hover-with-fade wd-quantity-overlap product-grid-item product type-product post-292835 status-publish last instock product_cat-belleza product_cat-hogar has-post-thumbnail shipping-taxable purchasable product-type-simple" data-loop="71" data-id="292835">
<div class="wd-product-wrapper product-wrapper">
<div class="wd-product-thumb product-element-top wd-quick-shop">
<a href="https://www.tustecnologiastuc.com/product/tabla-para-flexiones-multifuncional-gimnasio/" class="wd-product-img-link product-image-link">
<img width="339" height="387" src="https://www.tustecnologiastuc.com/wp-content/uploads/WhatsApp-Image-2026-08-06-at-10.56.42-AM-339x387.jpeg" class="attachment-339x387 size-339x387" alt="TABLA PARA FLEXIONES MULTIFUNCIONAL GIMNASIO" />
</a>
</div>
<div class="product-element-bottom">
<h3 class="wd-entities-title"><a href="https://www.tustecnologiastuc.com/product/tabla-para-flexiones-multifuncional-gimnasio/">TABLA PARA FLEXIONES MULTIFUNCIONAL GIMNASIO</a></h3>
<p class="wd-product-stock stock wd-style-default in-stock">17 en stock</p>
<div class="wrap-price">
<span class="price"><span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">$</span>20,500.00</bdi></span></span>
</div>
<div class="wd-add-btn wd-add-btn-replace">
<div class="quantity">
<label>CANTIDAD</label>
<input type="number" class="input-text qty text" value="1" min="1" max="17" name="quantity" />
</div>
<a href="/wp-admin/admin-ajax.php?add-to-cart=292835" data-quantity="1" class="button product_type_simple add_to_cart_button" data-product_id="292835">Añadir al carrito</a>
</div>
</div>
</div>
</div>
HTML;

    #[Test]
    public function it_parses_tuc_products_with_us_price_format(): void
    {
        Http::fake([
            'www.tustecnologiastuc.com/*' => Http::response([
                'items' => self::TUC_PRODUCT_HTML,
                'nextPage' => null,
                'currentPage' => 'https://www.tustecnologiastuc.com/productos/',
                'resultCount' => 'Mostrando 1–1 de 1 resultados',
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $scraper = new TucScraperService();
        $result = $scraper->fetchPage('https://www.tustecnologiastuc.com/productos/');

        $this->assertCount(1, $result['products']);
        $p = $result['products'][0];

        // external_id sin prefijo (raw WooCommerce ID)
        $this->assertSame('292835', $p['external_id']);

        // Nombre
        $this->assertSame('TABLA PARA FLEXIONES MULTIFUNCIONAL GIMNASIO', $p['name']);

        // Source URL
        $this->assertSame(
            'https://www.tustecnologiastuc.com/product/tabla-para-flexiones-multifuncional-gimnasio/',
            $p['source_url']
        );

        // Precio formato US: $20,500.00 → 20500.00
        $this->assertSame(20500.0, $p['price']);
        $this->assertNull($p['list_price']);
        $this->assertSame(20500.0, $p['cash_price']);

        // Stock del HTML "17 en stock"
        $this->assertSame(17, $p['stock']);

        // Categorías extraídas del class attribute (slugs → nombres)
        $this->assertEqualsCanonicalizing(['Belleza', 'Hogar'], $p['categories_external']);

        // Imagen
        $this->assertStringContainsString('WhatsApp-Image', $p['image']);

        // Tuc no tiene marca
        $this->assertNull($p['brand']);

        // Origin
        $this->assertSame('tuc', $p['origin']);
    }

    #[Test]
    public function it_parses_multiple_products_from_a_page(): void
    {
        $html = self::TUC_PRODUCT_HTML . self::TUC_PRODUCT_HTML;

        Http::fake([
            'www.tustecnologiastuc.com/*' => Http::response([
                'items' => $html,
                'nextPage' => null,
                'currentPage' => 'https://www.tustecnologiastuc.com/productos/',
                'resultCount' => 'Mostrando 1–2 de 2 resultados',
            ], 200),
        ]);

        $scraper = new TucScraperService();
        $result = $scraper->fetchPage('https://www.tustecnologiastuc.com/productos/');

        $this->assertCount(2, $result['products']);
        $this->assertSame('292835', $result['products'][0]['external_id']);
        $this->assertSame('292835', $result['products'][1]['external_id']);
    }

    #[Test]
    public function it_parses_zero_stock_products(): void
    {
        // Cambiamos el texto a "Sin stock" Y eliminamos el input.qty
        // que tiene prioridad en el parser.
        $html = str_replace(
            'in-stock">17 en stock',
            'out-of-stock">Sin stock',
            self::TUC_PRODUCT_HTML
        );
        $html = preg_replace(
            '/<input[^>]*class="input-text qty text"[^>]*>/i',
            '',
            $html
        );

        Http::fake([
            'www.tustecnologiastuc.com/*' => Http::response([
                'items' => $html,
                'nextPage' => null,
            ], 200),
        ]);

        $scraper = new TucScraperService();
        $result = $scraper->fetchPage('https://www.tustecnologiastuc.com/productos/');

        $this->assertSame(0, $result['products'][0]['stock']);
    }

    #[Test]
    public function it_handles_decimal_prices(): void
    {
        $html = str_replace(
            '<span class="woocommerce-Price-currencySymbol">$</span>20,500.00',
            '<span class="woocommerce-Price-currencySymbol">$</span>2,383.11',
            self::TUC_PRODUCT_HTML
        );

        Http::fake([
            'www.tustecnologiastuc.com/*' => Http::response([
                'items' => $html,
                'nextPage' => null,
            ], 200),
        ]);

        $scraper = new TucScraperService();
        $result = $scraper->fetchPage('https://www.tustecnologiastuc.com/productos/');

        $this->assertEquals(2383.11, $result['products'][0]['price']);
    }

    #[Test]
    public function it_handles_avif_images(): void
    {
        $html = str_replace(
            'https://www.tustecnologiastuc.com/wp-content/uploads/WhatsApp-Image-2026-08-06-at-10.56.42-AM-339x387.jpeg',
            'https://www.tustecnologiastuc.com/wp-content/uploads/test-image.avif',
            self::TUC_PRODUCT_HTML
        );

        Http::fake([
            'www.tustecnologiastuc.com/*' => Http::response([
                'items' => $html,
                'nextPage' => null,
            ], 200),
        ]);

        $scraper = new TucScraperService();
        $result = $scraper->fetchPage('https://www.tustecnologiastuc.com/productos/');

        $this->assertSame('https://www.tustecnologiastuc.com/wp-content/uploads/test-image.avif', $result['products'][0]['image']);
    }

    #[Test]
    public function it_returns_empty_array_on_empty_items(): void
    {
        Http::fake([
            'www.tustecnologiastuc.com/*' => Http::response([
                'items' => '',
                'nextPage' => null,
            ], 200),
        ]);

        $scraper = new TucScraperService();
        $result = $scraper->fetchPage('https://www.tustecnologiastuc.com/productos/');

        $this->assertSame([], $result['products']);
    }

    #[Test]
    public function it_throws_on_invalid_json_response(): void
    {
        Http::fake([
            'www.tustecnologiastuc.com/*' => Http::response('not json', 200),
        ]);

        $this->expectException(\RuntimeException::class);

        $scraper = new TucScraperService();
        $scraper->fetchPage('https://www.tustecnologiastuc.com/productos/');
    }

    #[Test]
    public function it_throws_on_http_error(): void
    {
        Http::fake([
            'www.tustecnologiastuc.com/*' => Http::response('Server Error', 500),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/HTTP 500/');

        $scraper = new TucScraperService();
        $scraper->fetchPage('https://www.tustecnologiastuc.com/productos/');
    }
}

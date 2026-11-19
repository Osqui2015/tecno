<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresión: un scraping fallido NO debe vaciar el catálogo.
 *
 * Contexto: cuando el scraper fallaba (por ejemplo, por un error de TLS
 * que el servidor respondía como 403), el comando igual marcaba TODOS los
 * productos existentes como faltantes (active=0, stock=0, missing_since),
 * dejando la tienda sin productos por un fallo transitorio de red.
 */
class ScraperMissingGuardTest extends TestCase
{
    use RefreshDatabase;

    private function existingTucProduct(string $externalId = '292835'): Product
    {
        return Product::factory()->create([
            'external_id' => $externalId,
            'origin'      => 'tuc',
            'name'        => 'PRODUCTO EXISTENTE',
            'price'       => 1000,
            'stock'       => 5,
            'active'      => true,
        ]);
    }

    #[Test]
    public function a_failed_scrape_does_not_hide_existing_products(): void
    {
        $product = $this->existingTucProduct();

        // El servidor responde 403 en todas las páginas.
        Http::fake([
            'www.tustecnologiastuc.com/*' => Http::response('Forbidden', 403),
        ]);

        $this->artisan('tuc:scrape')
            ->assertExitCode(0);

        $product->refresh();

        // 🔑 El producto NO debe haber sido marcado como faltante.
        $this->assertTrue(
            (bool) $product->active,
            'El producto no debe desactivarse si el scraping falló.'
        );
        $this->assertSame(5, (int) $product->stock, 'El stock no debe zerearse si el scraping falló.');
        $this->assertNull(
            $product->missing_since,
            'El producto no debe marcarse como faltante si el scraping falló.'
        );
    }

    #[Test]
    public function an_empty_scrape_does_not_hide_existing_products(): void
    {
        $product = $this->existingTucProduct();

        // Responde 200 pero sin productos.
        Http::fake([
            'www.tustecnologiastuc.com/*' => Http::response([
                'items'       => '',
                'nextPage'    => null,
                'resultCount' => 'Mostrando 0–0 de 0 resultados',
            ], 200),
        ]);

        $this->artisan('tuc:scrape')
            ->assertExitCode(0);

        $product->refresh();

        $this->assertTrue(
            (bool) $product->active,
            'El producto no debe desactivarse si el scraping no devolvió nada.'
        );
        $this->assertSame(5, (int) $product->stock);
        $this->assertNull($product->missing_since);
    }

    #[Test]
    public function a_successful_scrape_hides_products_that_disappeared(): void
    {
        // Este producto existe en la DB pero ya no está en el origen.
        $ghost = $this->existingTucProduct('999999');

        $html = <<<'HTML'
<div class="wd-product wd-col wd-hover-fw-button product-grid-item product type-product post-292835 status-publish instock product_cat-hogar has-post-thumbnail product-type-simple" data-id="292835">
<div class="wd-product-thumb">
<a href="https://www.tustecnologiastuc.com/producto/visible/"><img src="https://www.tustecnologiastuc.com/wp-content/uploads/visible.jpeg" class="attachment-339x387" alt="PRODUCTO VISIBLE" /></a>
</div>
<div class="product-element-bottom">
<h3 class="wd-entities-title"><a href="https://www.tustecnologiastuc.com/producto/visible/">PRODUCTO VISIBLE</a></h3>
<p class="wd-product-stock stock wd-style-default in-stock">17 en stock</p>
<div class="wrap-price"><span class="price"><span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">$</span>20,500.00</bdi></span></span></div>
</div>
</div>
HTML;

        Http::fake([
            'www.tustecnologiastuc.com/*' => Http::response([
                'items'       => $html,
                'nextPage'    => null,
                'resultCount' => 'Mostrando 1–1 de 1 resultados',
            ], 200),
        ]);

        $this->artisan('tuc:scrape')
            ->assertExitCode(0);

        $ghost->refresh();

        // Con un scrape exitoso, el producto que no volvió SÍ debe ocultarse.
        $this->assertFalse(
            (bool) $ghost->active,
            'Un producto que no aparece en un scrape exitoso debe ocultarse.'
        );
        $this->assertSame(0, (int) $ghost->stock);
        $this->assertNotNull($ghost->missing_since);
    }
}

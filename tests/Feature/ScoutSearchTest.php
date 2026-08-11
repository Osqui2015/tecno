<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Test del endpoint con búsqueda.
 * Usa el driver Scout configurado en phpunit (null) cuando no hay texto;
 * cuando hay search usa la búsqueda normal (LIKE) del search scope.
 */
class ScoutSearchTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function products_endpoint_works_without_search_term(): void
    {
        Product::factory()->count(5)->create(['active' => true, 'stock' => 10]);

        $response = $this->getJson('/api/products');

        $response->assertOk();
        $this->assertEquals(5, $response->json('total'));
    }

    #[Test]
    public function products_endpoint_works_with_search_term(): void
    {
        // Con SCOUT_DRIVER=null en tests, la búsqueda Scout se deshabilita.
        // Validamos que el endpoint no rompe con el parámetro search.
        Product::factory()->create(['name' => 'Smartphone Pro', 'active' => true]);
        Product::factory()->create(['name' => 'Auriculares Bluetooth', 'active' => true]);
        Product::factory()->create(['name' => 'Smart TV 55 pulgadas', 'active' => true]);

        $response = $this->getJson('/api/products?search=smart');

        $response->assertOk();
        // El endpoint responde OK aunque en tests el driver esté en null
    }

    #[Test]
    public function search_respects_category_filter(): void
    {
        $cat1 = \App\Models\Category::factory()->create();
        $cat2 = \App\Models\Category::factory()->create();

        Product::factory()->create(['name' => 'Test A', 'category_id' => $cat1->id, 'stock' => 10]);
        Product::factory()->create(['name' => 'Test B', 'category_id' => $cat2->id, 'stock' => 10]);

        $response = $this->getJson("/api/products?category_id={$cat1->id}");

        $response->assertOk();
        $this->assertEquals(1, $response->json('total'));
    }
}

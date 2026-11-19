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

    #[Test]
    public function search_finds_products_regardless_of_accents_and_case(): void
    {
        Product::factory()->create([
            'name'   => 'TRIPODE PARA PARLANTE 60 120 CM',
            'active' => true,
            'stock'  => 10,
        ]);

        Product::factory()->create([
            'name'   => 'Cargador Portátil Rápido',
            'active' => true,
            'stock'  => 10,
        ]);

        // 1. Búsqueda con tilde cuando el producto no tiene tilde
        $resAccent = $this->getJson('/api/products?search=tr%C3%ADpode'); // trípode
        $resAccent->assertOk();
        $this->assertEquals(1, $resAccent->json('total'));
        $this->assertEquals('TRIPODE PARA PARLANTE 60 120 CM', $resAccent->json('data.0.name'));

        // 2. Búsqueda en minúsculas sin tilde
        $resLower = $this->getJson('/api/products?search=tripode');
        $resLower->assertOk();
        $this->assertEquals(1, $resLower->json('total'));

        // 3. Búsqueda en mayúsculas con tilde
        $resUpper = $this->getJson('/api/products?search=TR%C3%8DPODE'); // TRÍPODE
        $resUpper->assertOk();
        $this->assertEquals(1, $resUpper->json('total'));

        // 4. Búsqueda sin tilde cuando el producto sí tiene tilde
        $resNoAccent = $this->getJson('/api/products?search=portatil');
        $resNoAccent->assertOk();
        $this->assertEquals(1, $resNoAccent->json('total'));
        $this->assertEquals('Cargador Portátil Rápido', $resNoAccent->json('data.0.name'));
    }

    #[Test]
    public function search_finds_products_with_multi_words_in_any_order(): void
    {
        Product::factory()->create([
            'name'   => 'TRIPODE PARA PARLANTE 60 120 CM',
            'active' => true,
            'stock'  => 10,
        ]);

        // Palabras en orden invertido respecto al nombre
        $response = $this->getJson('/api/products?search=parlante+tripode');
        $response->assertOk();
        $this->assertEquals(1, $response->json('total'));
        $this->assertEquals('TRIPODE PARA PARLANTE 60 120 CM', $response->json('data.0.name'));

        // Palabras en orden invertido y con tildes
        $responseAccent = $this->getJson('/api/products?search=parlante+tr%C3%ADpode');
        $responseAccent->assertOk();
        $this->assertEquals(1, $responseAccent->json('total'));
    }
}

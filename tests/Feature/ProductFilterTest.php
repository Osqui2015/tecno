<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductFilterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function can_filter_products_by_price_range(): void
    {
        Product::factory()->create(['name' => 'Barato', 'price' => 100, 'stock' => 10, 'active' => true, 'external_id' => 'EXT1']);
        Product::factory()->create(['name' => 'Caro', 'price' => 1000, 'stock' => 10, 'active' => true, 'external_id' => 'EXT2']);

        $response = $this->getJson('/api/products?min_price=500');

        $response->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.name', 'Caro');
    }

    #[Test]
    public function can_filter_products_by_brand(): void
    {
        Product::factory()->create(['name' => 'Mouse', 'brand' => 'Logitech', 'stock' => 10, 'active' => true, 'external_id' => 'EXT1']);
        Product::factory()->create(['name' => 'Teclado', 'brand' => 'Razer', 'stock' => 10, 'active' => true, 'external_id' => 'EXT2']);

        $response = $this->getJson('/api/products?brand=Logitech');

        $response->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.name', 'Mouse');
    }

    #[Test]
    public function can_sort_products_by_price_descending(): void
    {
        Product::factory()->create(['name' => 'Barato', 'price' => 100, 'stock' => 10, 'active' => true, 'external_id' => 'EXT1']);
        Product::factory()->create(['name' => 'Caro', 'price' => 1000, 'stock' => 10, 'active' => true, 'external_id' => 'EXT2']);

        $response = $this->getJson('/api/products?sort_by=price_desc');

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Caro')
            ->assertJsonPath('data.1.name', 'Barato');
    }

    #[Test]
    public function can_sort_products_by_featured_prioritizing_christmas_products(): void
    {
        $catNavidad = \App\Models\Category::factory()->create(['name' => 'Navidad']);
        $catGeneral = \App\Models\Category::factory()->create(['name' => 'Ferretería']);

        Product::factory()->create([
            'name'        => 'Adaptador de Enchufe',
            'category_id' => $catGeneral->id,
            'stock'       => 1000,
            'image'       => 'https://example.com/plug.jpg',
            'active'      => true,
            'external_id' => 'GEN1',
        ]);

        Product::factory()->create([
            'name'        => 'Luces Navideñas 100L',
            'category_id' => $catNavidad->id,
            'stock'       => 50,
            'image'       => 'https://example.com/luces.jpg',
            'active'      => true,
            'external_id' => 'NAV1',
        ]);

        Product::factory()->create([
            'name'        => 'Árbol de Navidad 120cm',
            'category_id' => $catNavidad->id,
            'stock'       => 20,
            'image'       => 'https://example.com/arbol.jpg',
            'active'      => true,
            'external_id' => 'NAV2',
        ]);

        $response = $this->getJson('/api/products?sort_by=featured');

        $response->assertOk();
        // El árbol de navidad va primero (hero), luego las luces navideñas, y luego el adaptador
        $this->assertEquals('Árbol de Navidad 120cm', $response->json('data.0.name'));
        $this->assertEquals('Luces Navideñas 100L', $response->json('data.1.name'));
        $this->assertEquals('Adaptador de Enchufe', $response->json('data.2.name'));
    }
}

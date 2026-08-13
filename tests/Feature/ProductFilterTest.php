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
}

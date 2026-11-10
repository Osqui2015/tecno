<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SeoRouteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function product_by_slug_redirects_301_when_slug_mismatches(): void
    {
        $product = Product::factory()->create([
            'name'   => 'Monitor Gamer 24',
            'slug'   => 'monitor-gamer-24',
            'active' => true,
            'stock'  => 10,
        ]);

        $response = $this->get("/productos/slug-incorrecto/{$product->id}");

        $response->assertStatus(301);
        $response->assertRedirect(url("/productos/{$product->slug}/{$product->id}"));
    }

    #[Test]
    public function product_by_slug_renders_when_slug_matches(): void
    {
        $product = Product::factory()->create([
            'name'   => 'Monitor Gamer 24',
            'slug'   => 'monitor-gamer-24',
            'active' => true,
            'stock'  => 10,
        ]);

        $response = $this->get("/productos/{$product->slug}/{$product->id}");

        $response->assertOk();
    }

    #[Test]
    public function category_seo_url_uses_category_slug(): void
    {
        $category = Category::factory()->create([
            'name' => 'Audio & Video',
            'slug' => 'audio-video',
        ]);

        $response = $this->get("/categorias/{$category->slug}");

        $response->assertOk();
        $response->assertViewHas('seoUrl', url("/productos/{$category->slug}"));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminProductTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    // ============================================================
    //  CRUD
    // ============================================================

    #[Test]
    public function admin_can_list_all_products_including_inactive(): void
    {
        $admin = $this->admin();
        Product::factory()->count(3)->create(['active' => true]);
        Product::factory()->count(2)->inactive()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/products');

        $response->assertOk();
        $this->assertEquals(5, $response->json('total'));
    }

    #[Test]
    public function admin_can_filter_by_source_daz(): void
    {
        $admin = $this->admin();
        Product::factory()->count(3)->fromDaz()->create();
        Product::factory()->count(2)->create(['external_id' => null]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/products?source=daz');

        $this->assertEquals(3, $response->json('total'));
    }

    #[Test]
    public function admin_can_filter_by_source_manual(): void
    {
        $admin = $this->admin();
        Product::factory()->count(2)->fromDaz()->create();
        Product::factory()->count(4)->create(['external_id' => null]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/products?source=manual');

        $this->assertEquals(4, $response->json('total'));
    }

    #[Test]
    public function admin_can_filter_by_stock_status(): void
    {
        $admin = $this->admin();
        Product::factory()->count(3)->create(['stock' => 5]);
        Product::factory()->count(2)->outOfStock()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/products?stock_status=out_of_stock');

        $this->assertEquals(2, $response->json('total'));
    }

    #[Test]
    public function admin_can_search_products(): void
    {
        $admin = $this->admin();
        Product::factory()->create(['name' => 'Auriculares Bluetooth']);
        Product::factory()->create(['name' => 'Smartphone Pro']);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/products?search=Auriculares');

        $this->assertEquals(1, $response->json('total'));
        $this->assertEquals('Auriculares Bluetooth', $response->json('data.0.name'));
    }

    #[Test]
    public function admin_can_create_product(): void
    {
        $admin = $this->admin();
        $cat = Category::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/products', [
                'name'        => 'Notebook 14 pulgadas',
                'description' => 'Liviana y potente',
                'price'       => 999999,
                'stock'       => 10,
                'category_id' => $cat->id,
                'markup_percent' => 20,
            ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'Notebook 14 pulgadas')
            ->assertJsonPath('markup_percent', '20.00')
            ->assertJsonPath('final_price', 1199998.8); // 999999 * 1.20 (JSON sin .0 si entero)
    }

    #[Test]
    public function admin_can_update_product_including_markup(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create(['price' => 1000, 'markup_percent' => 0]);

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/products/{$product->id}", [
                'markup_percent' => 50,
                'stock'          => 99,
            ]);

        $response->assertOk()
            ->assertJsonPath('markup_percent', '50.00')
            ->assertJsonPath('final_price', 1500) // 1000 * 1.50 = 1500 (JSON lo serializa como int)
            ->assertJsonPath('stock', 99);
    }

    #[Test]
    public function admin_can_delete_product(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/products/{$product->id}")
            ->assertOk();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    // ============================================================
    //  BULK MARKUP
    // ============================================================

    #[Test]
    public function admin_can_apply_bulk_markup_to_all_products(): void
    {
        $admin = $this->admin();
        Product::factory()->count(5)->create(['markup_percent' => 0]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/products/bulk-markup', [
                'percent' => 25,
            ]);

        $response->assertOk()
            ->assertJsonPath('updated', 5);

        $this->assertEquals(5, Product::where('markup_percent', 25)->count());
    }

    #[Test]
    public function admin_can_apply_bulk_markup_to_specific_products(): void
    {
        $admin = $this->admin();
        $a = Product::factory()->create(['markup_percent' => 0]);
        $b = Product::factory()->create(['markup_percent' => 0]);
        $c = Product::factory()->create(['markup_percent' => 0]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/products/bulk-markup', [
                'percent'     => 30,
                'product_ids' => [$a->id, $b->id],
            ]);

        $response->assertOk()
            ->assertJsonPath('updated', 2);

        $this->assertEquals(30, (float) $a->fresh()->markup_percent);
        $this->assertEquals(30, (float) $b->fresh()->markup_percent);
        $this->assertEquals(0,  (float) $c->fresh()->markup_percent); // intacto
    }

    #[Test]
    public function admin_can_apply_bulk_markup_only_to_daz_products(): void
    {
        $admin = $this->admin();
        Product::factory()->count(3)->fromDaz()->create();
        Product::factory()->count(2)->create(['external_id' => null]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/products/bulk-markup', [
                'percent' => 15,
                'source'  => 'daz',
            ]);

        $response->assertOk()
            ->assertJsonPath('updated', 3);

        $this->assertEquals(15, (float) Product::whereNotNull('external_id')->first()->markup_percent);
        $this->assertEquals(0,  (float) Product::whereNull('external_id')->first()->markup_percent);
    }

    #[Test]
    public function buyer_cannot_apply_bulk_markup(): void
    {
        $buyer = User::factory()->create();

        $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/admin/products/bulk-markup', ['percent' => 50])
            ->assertStatus(403);
    }

    #[Test]
    public function bulk_markup_requires_percent_field(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/products/bulk-markup', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['percent']);
    }
}

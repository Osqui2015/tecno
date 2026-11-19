<?php

namespace Tests\Feature;

use App\Models\CatalogSetting;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Verifica la configuración de visibilidad del catálogo:
 * qué proveedores y categorías se muestran en el sitio público.
 */
class CatalogVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $origin, ?Category $category = null, string $name = 'Prod'): Product
    {
        $category ??= Category::factory()->create();

        return Product::factory()->create([
            'origin'      => $origin,
            'category_id' => $category->id,
            'name'        => $name,
            'stock'       => 10,
            'active'      => true,
        ]);
    }

    #[Test]
    public function by_default_all_origins_are_visible(): void
    {
        $daz  = $this->product('daz');
        $tuc  = $this->product('tuc');
        $tero = $this->product('tero');

        $ids = Product::available()
            ->tap(fn ($q) => \App\Services\CatalogVisibility::apply($q))
            ->pluck('id');

        $this->assertTrue($ids->contains($daz->id));
        $this->assertTrue($ids->contains($tuc->id));
        $this->assertTrue($ids->contains($tero->id));
    }

    #[Test]
    public function a_disabled_origin_is_hidden_from_the_catalog(): void
    {
        $daz = $this->product('daz');
        $tuc = $this->product('tuc');

        $setting = CatalogSetting::forOrigin('daz');
        $setting->enabled = false;
        $setting->save();

        \App\Services\CatalogVisibility::flushCache();

        $ids = Product::available()
            ->tap(fn ($q) => \App\Services\CatalogVisibility::apply($q))
            ->pluck('id');

        $this->assertFalse($ids->contains($daz->id), 'El proveedor deshabilitado no debe verse.');
        $this->assertTrue($ids->contains($tuc->id), 'Los demás proveedores deben seguir viéndose.');
    }

    #[Test]
    public function filtering_by_category_only_shows_the_selected_ones(): void
    {
        $hogar    = Category::factory()->create(['name' => 'Hogar']);
        $celular  = Category::factory()->create(['name' => 'Celulares']);

        $pHogar   = $this->product('tuc', $hogar, 'Prod Hogar');
        $pCelular = $this->product('tuc', $celular, 'Prod Celular');
        $pOtro    = $this->product('daz', $hogar, 'Prod Daz');

        $setting = CatalogSetting::forOrigin('tuc');
        $setting->enabled            = true;
        $setting->filter_by_category = true;
        $setting->category_ids       = [$hogar->id];
        $setting->save();

        \App\Services\CatalogVisibility::flushCache();

        $ids = Product::available()
            ->tap(fn ($q) => \App\Services\CatalogVisibility::apply($q))
            ->pluck('id');

        $this->assertTrue($ids->contains($pHogar->id), 'La categoría seleccionada debe verse.');
        $this->assertFalse($ids->contains($pCelular->id), 'La categoría no seleccionada no debe verse.');
        $this->assertTrue($ids->contains($pOtro->id), 'Otros proveedores no se ven afectados.');
    }

    #[Test]
    public function manual_products_are_visible_by_default(): void
    {
        $manual = Product::factory()->create([
            'origin'      => 'manual',
            'name'        => 'Prod Manual',
            'stock'       => 10,
            'active'      => true,
        ]);

        $ids = Product::available()
            ->tap(fn ($q) => \App\Services\CatalogVisibility::apply($q))
            ->pluck('id');

        $this->assertTrue($ids->contains($manual->id));
    }

    #[Test]
    public function admin_can_read_and_update_the_catalog_settings(): void
    {
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncRoles(['admin']);

        $category = Category::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/catalog-settings');

        $response->assertOk();
        $this->assertCount(4, $response->json('settings'));
        $this->assertNotEmpty($response->json('categories'));

        // Actualizar: ocultar daz y filtrar tuc por una categoría.
        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/admin/catalog-settings', [
                'settings' => [
                    [
                        'origin'             => 'daz',
                        'enabled'            => false,
                        'filter_by_category' => false,
                        'category_ids'       => [],
                        'sort_order'         => 0,
                    ],
                    [
                        'origin'             => 'tuc',
                        'enabled'            => true,
                        'filter_by_category' => true,
                        'category_ids'       => [$category->id],
                        'sort_order'         => 1,
                    ],
                ],
            ]);

        $response->assertOk();

        $this->assertFalse((bool) CatalogSetting::forOrigin('daz')->enabled);
        $this->assertTrue((bool) CatalogSetting::forOrigin('tuc')->filter_by_category);
        $this->assertSame([$category->id], CatalogSetting::forOrigin('tuc')->allowedCategoryIds());
    }

    #[Test]
    public function non_admin_cannot_read_the_catalog_settings(): void
    {
        $buyer = \App\Models\User::factory()->create(['role' => 'comprador']);

        $this->actingAs($buyer, 'sanctum')
            ->getJson('/api/admin/catalog-settings')
            ->assertForbidden();
    }
}

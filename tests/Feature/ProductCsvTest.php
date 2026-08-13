<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductCsvTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::factory()->admin()->create();
    }

    #[Test]
    public function admin_can_export_products_to_csv(): void
    {
        $admin = $this->createAdmin();
        Product::factory()->create(['name' => 'Producto CSV 1', 'price' => 500]);

        $response = $this->actingAs($admin, 'sanctum')
            ->get('/api/admin/products/export/csv');

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    #[Test]
    public function admin_can_import_products_from_csv(): void
    {
        $admin = $this->createAdmin();
        $category = Category::factory()->create();

        $csvContent = "SKU,Nombre,Marca,Precio Base,Stock,Activo,ID Categoria\n" .
                      "SKU-TEST-100,Producto Importado 1,Kingston,1200,15,SI,{$category->id}\n";

        $file = UploadedFile::fake()->createWithContent('productos.csv', $csvContent);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/products/import/csv', [
                'file' => $file,
            ]);

        $response->assertOk()
            ->assertJsonPath('created', 1);

        $this->assertDatabaseHas('products', [
            'sku'  => 'SKU-TEST-100',
            'name' => 'Producto Importado 1',
        ]);
    }
}

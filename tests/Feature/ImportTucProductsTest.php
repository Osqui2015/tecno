<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImportTucProductsTest extends TestCase
{
    use RefreshDatabase;

    private string $tempJsonPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempJsonPath = storage_path('app/temp_test_productos.json');
    }

    protected function tearDown(): void
    {
        if (File::exists($this->tempJsonPath)) {
            File::delete($this->tempJsonPath);
        }
        parent::tearDown();
    }

    #[Test]
    public function it_imports_new_products_from_json(): void
    {
        $data = [
            [
                'external_id'     => '999001',
                'nombre'          => 'Smartwatch Tuc Test',
                'precio'          => '15500.50',
                'precio_anterior' => '18000.00',
                'imagen_url'      => 'https://example.com/smartwatch.jpg',
                'url_producto'    => 'https://example.com/smartwatch',
                'categoria'       => 'Relojes Inteligentes',
                'sku'             => 'SMART-001',
                'stock'           => '10',
            ],
            [
                'external_id'     => '999002',
                'nombre'          => 'Auriculares Tuc Test',
                'precio'          => '8200.00',
                'precio_anterior' => null,
                'imagen_url'      => 'https://example.com/auris.jpg',
                'url_producto'    => 'https://example.com/auris',
                'categoria'       => 'Audio',
                'sku'             => 'AURI-002',
                'stock'           => '0', // Stock cero -> debe quedar inactivo
            ],
        ];

        File::put($this->tempJsonPath, json_encode($data));

        $this->artisan('tuc:import', ['--file' => 'storage/app/temp_test_productos.json'])
            ->expectsOutputToContain('Total de productos encontrados en el JSON: 2')
            ->expectsOutputToContain('Nuevos creados')
            ->assertExitCode(0);

        // Verificar el primer producto
        $p1 = Product::where('external_id', '999001')->where('origin', 'tuc')->first();
        $this->assertNotNull($p1);
        $this->assertSame('Smartwatch Tuc Test', $p1->name);
        $this->assertEquals(15500.50, (float) $p1->price);
        $this->assertEquals(18000.00, (float) $p1->list_price);
        $this->assertSame('https://example.com/smartwatch.jpg', $p1->image);
        $this->assertSame('SMART-001', $p1->sku);
        $this->assertSame(10, $p1->stock);
        $this->assertTrue($p1->active);

        // Verificar que la categoría se creó
        $cat = Category::where('name', 'Relojes Inteligentes')->first();
        $this->assertNotNull($cat);
        $this->assertSame($cat->id, $p1->category_id);

        // Verificar el producto con stock 0 (debe estar inactivo)
        $p2 = Product::where('external_id', '999002')->where('origin', 'tuc')->first();
        $this->assertNotNull($p2);
        $this->assertSame(0, $p2->stock);
        $this->assertFalse($p2->active);
    }

    #[Test]
    public function it_updates_existing_products_without_deleting_missing_ones(): void
    {
        $cat = Category::create(['name' => 'General', 'slug' => 'general']);

        // Producto existente previo
        $existing = Product::create([
            'external_id' => '999001',
            'origin'      => 'tuc',
            'name'        => 'Viejo Nombre',
            'price'       => 10000.00,
            'stock'       => 5,
            'active'      => true,
            'category_id' => $cat->id,
        ]);

        // Otro producto previo que NO viene en el JSON
        $other = Product::create([
            'external_id' => '888888',
            'origin'      => 'tuc',
            'name'        => 'Producto No Incluido en JSON',
            'price'       => 5000.00,
            'stock'       => 3,
            'active'      => true,
            'category_id' => $cat->id,
        ]);

        $data = [
            [
                'external_id' => '999001',
                'nombre'      => 'Nuevo Nombre Actualizado',
                'precio'      => '12500.00',
                'stock'       => '20',
            ],
        ];

        File::put($this->tempJsonPath, json_encode($data));

        $this->artisan('tuc:import', ['--file' => 'storage/app/temp_test_productos.json'])
            ->assertExitCode(0);

        // Verificar actualización
        $existing->refresh();
        $this->assertSame('Nuevo Nombre Actualizado', $existing->name);
        $this->assertEquals(12500.00, (float) $existing->price);
        $this->assertSame(20, $existing->stock);

        // Verificar que el producto no incluido NO fue eliminado ni desactivado
        $other->refresh();
        $this->assertTrue($other->active);
        $this->assertSame(3, $other->stock);
    }

    #[Test]
    public function it_supports_dry_run_without_persisting(): void
    {
        $data = [
            [
                'external_id' => '777001',
                'nombre'      => 'Producto Simulado',
                'precio'      => '1000.00',
            ],
        ];

        File::put($this->tempJsonPath, json_encode($data));

        $this->artisan('tuc:import', [
            '--file'    => 'storage/app/temp_test_productos.json',
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('MODO DRY-RUN')
            ->assertExitCode(0);

        $this->assertNull(Product::where('external_id', '777001')->first());
    }
}

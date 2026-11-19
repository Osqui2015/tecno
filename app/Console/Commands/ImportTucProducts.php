<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Comando para importar productos de TusTecnología Tuc desde un archivo JSON local.
 *
 * Flujo:
 *   1. Ejecutar localmente: `node scraper.js` (en carpeta tuc-scraper)
 *   2. Subir `productos.json` a `storage/app/productos.json` en el servidor
 *   3. Ejecutar: `php artisan tuc:import`
 *
 * Uso:
 *   php artisan tuc:import
 *   php artisan tuc:import --file=storage/app/productos.json
 *   php artisan tuc:import --file=tuc-scraper/productos_test.json
 *   php artisan tuc:import --dry-run
 */
class ImportTucProducts extends Command
{
    protected $signature = 'tuc:import
        {--file=    : Ruta al archivo JSON (default: storage/app/productos.json)}
        {--dry-run : Simular la importación sin guardar cambios en la base de datos}';

    protected $description = 'Importa y sincroniza productos desde el archivo productos.json generado por el scraper local';

    public function handle(): int
    {
        $this->newLine();
        $this->info('╔══════════════════════════════════════════╗');
        $this->info('║   TUSTECNOLOGÍATUC — IMPORTADOR JSON    ║');
        $this->info('╚══════════════════════════════════════════╝');
        $this->newLine();

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('🧪 MODO DRY-RUN: Simulación activa, no se modificarán datos.');
        }

        $filePath = $this->resolveFilePath();
        if (! file_exists($filePath)) {
            $this->error("❌ No se encontró el archivo JSON en: {$filePath}");
            $this->line('   Asegurate de haber subido el archivo generado por el scraper local.');
            return self::FAILURE;
        }

        $this->info("📂 Leyendo archivo: {$filePath}");
        $content = file_get_contents($filePath);
        $items = json_decode($content, true);

        if (! is_array($items)) {
            $this->error('❌ El archivo no contiene un JSON válido o está vacío.');
            return self::FAILURE;
        }

        $totalItems = count($items);
        $this->info("🔍 Total de productos encontrados en el JSON: {$totalItems}");

        if ($totalItems === 0) {
            $this->warn('⚠️  El JSON no contiene ningún producto para importar.');
            return self::SUCCESS;
        }

        // Marcamos en el container que estamos importando
        app()->instance('scrape_in_progress', true);
        app()->instance('scrape_origin', 'tuc');

        $startTime = microtime(true);

        $stats = [
            'created'        => 0,
            'updated'        => 0,
            'skipped'        => 0,
            'zero_stock'     => 0,
            'new_categories' => 0,
            'errors'         => 0,
        ];

        // Cache de categorías existentes para evitar queries repetitivas
        $categoryCache = Category::pluck('id', 'name')->toArray();
        $initialCategoryCount = count($categoryCache);

        // Pre-cache de external_ids existentes en la DB para origin=tuc
        $existingProducts = Product::where('origin', 'tuc')
            ->pluck('id', 'external_id')
            ->toArray();

        // Desactivar Scout temporalmente durante la importación masiva
        config(['scout.driver' => null]);

        $this->newLine();
        $progressBar = $this->output->createProgressBar($totalItems);
        $progressBar->setFormat('   Procesando: %current%/%max% [%bar%] %percent:3s%% (ETA: %estimated:-6s%)');
        $progressBar->start();

        if (! $dryRun) {
            DB::beginTransaction();
        }

        try {
            foreach ($items as $item) {
                $progressBar->advance();

                // Mapear campos tanto de formato en español como en inglés
                $nombre = trim($item['nombre'] ?? $item['name'] ?? '');
                if ($nombre === '') {
                    $stats['skipped']++;
                    continue;
                }

                $rawPrice = $item['precio'] ?? $item['price'] ?? $item['cash_price'] ?? null;
                $rawListPrice = $item['precio_anterior'] ?? $item['list_price'] ?? null;

                $price = is_numeric($rawPrice) ? (float) $rawPrice : null;
                $listPrice = is_numeric($rawListPrice) ? (float) $rawListPrice : null;

                if ($price === null && $listPrice === null) {
                    $stats['skipped']++;
                    continue;
                }

                $finalPrice = $price ?? $listPrice;
                $cashPrice = $price ?? $listPrice;

                // ID externo (si no viene, creamos uno a partir de la URL o nombre)
                $externalId = (string) ($item['external_id'] ?? $item['id_externo'] ?? $item['id'] ?? '');
                if ($externalId === '') {
                    $url = $item['url_producto'] ?? $item['source_url'] ?? '';
                    $externalId = $url !== '' ? 'tuc_' . md5($url) : 'tuc_' . md5($nombre);
                }

                // Stock
                $rawStock = $item['stock'] ?? null;
                $stock = null;
                if ($rawStock !== null && $rawStock !== '' && is_numeric($rawStock)) {
                    $stock = (int) $rawStock;
                }
                $isOutOfStock = $stock === null || $stock <= 0;
                $finalStock = $stock !== null ? max(0, $stock) : 0;

                // Categoría
                $categoriaNombre = trim($item['categoria'] ?? $item['category'] ?? '');
                if ($categoriaNombre === '' && ! empty($item['categorias']) && is_array($item['categorias'])) {
                    $categoriaNombre = trim($item['categorias'][0]);
                }
                if ($categoriaNombre === '') {
                    $categoriaNombre = 'Importados Tuc';
                }

                $categoryId = null;
                if (! $dryRun) {
                    if (! isset($categoryCache[$categoriaNombre])) {
                        $catSlug = Str::slug($categoriaNombre);
                        $cat = Category::firstOrCreate(
                            ['slug' => $catSlug],
                            ['name' => $categoriaNombre, 'description' => 'Importado de TusTecnologíasTuc']
                        );
                        $categoryCache[$categoriaNombre] = $cat->id;
                    }
                    $categoryId = $categoryCache[$categoriaNombre];
                }

                $imagenUrl = trim($item['imagen_url'] ?? $item['image'] ?? '');
                $sourceUrl = trim($item['url_producto'] ?? $item['source_url'] ?? '');
                $sku = trim($item['sku'] ?? '');
                $descripcion = trim($item['descripcion'] ?? $item['description'] ?? '');
                $categoriesExternal = $item['categorias'] ?? [$categoriaNombre];

                $payload = [
                    'origin'              => 'tuc',
                    'name'                => $nombre,
                    'description'         => $descripcion !== '' ? $descripcion : null,
                    'image'               => $imagenUrl !== '' ? $imagenUrl : null,
                    'sku'                 => $sku !== '' ? $sku : null,
                    'stock'               => $finalStock,
                    'list_price'          => $listPrice,
                    'cash_price'          => $cashPrice,
                    'price'               => $finalPrice,
                    'source_url'          => $sourceUrl !== '' ? $sourceUrl : null,
                    'categories_external' => $categoriesExternal,
                    'category_id'         => $categoryId,
                    'active'              => ! $isOutOfStock,
                    'last_seen_at'        => now(),
                    'missing_since'       => null,
                ];

                if ($isOutOfStock) {
                    $stats['zero_stock']++;
                }

                if (isset($existingProducts[$externalId])) {
                    if (! $dryRun) {
                        $product = Product::find($existingProducts[$externalId]);
                        if ($product) {
                            $stockBefore = (int) $product->stock;
                            $product->fill($payload)->save();
                            $stockAfter = (int) $product->fresh()->stock;
                            if ($stockBefore !== $stockAfter) {
                                $product->recordStockChange('scraper', 'tuc:import');
                            }
                        }
                    }
                    $stats['updated']++;
                } else {
                    if (! $dryRun) {
                        $newProduct = Product::create(array_merge(['external_id' => $externalId], $payload));
                        $newProduct->recordStockChange('scraper', 'tuc:import:new');
                        $existingProducts[$externalId] = $newProduct->id;
                    }
                    $stats['created']++;
                }
            }

            if (! $dryRun) {
                DB::commit();
            }

        } catch (\Throwable $e) {
            if (! $dryRun) {
                DB::rollBack();
            }
            $progressBar->finish();
            $this->newLine(2);
            $this->error('❌ Error durante la transacción de importación: ' . $e->getMessage());
            Log::error('TucImport error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return self::FAILURE;
        }

        $progressBar->finish();
        $this->newLine(2);

        $stats['new_categories'] = max(0, count($categoryCache) - $initialCategoryCount);
        $elapsed = round(microtime(true) - $startTime, 2);

        \App\Support\CacheHelper::flush(['products:public']);

        $this->info('╔══════════════════════════════════════════╗');
        $this->info('║          ✅  REPORTE FINAL               ║');
        $this->info('╚══════════════════════════════════════════╝');
        $this->newLine();

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['⏱️  Tiempo de importación', "{$elapsed}s"],
                ['📦 Total productos en JSON', $totalItems],
                ['💾 Nuevos creados', $stats['created']],
                ['🔄 Existentes actualizados', $stats['updated']],
                ['📁 Categorías nuevas', $stats['new_categories']],
                ['🙈 Con stock = 0 (ocultos)', $stats['zero_stock']],
                ['⏭️  Omitidos (sin precio/nombre)', $stats['skipped']],
                ['⚠️  Errores', $stats['errors']],
            ]
        );

        $this->newLine();
        if ($dryRun) {
            $this->warn('🧪 Fin de la simulación. Ningún cambio fue persistido en la DB.');
        } else {
            $this->info('🎉 ¡Importación completada con éxito en la base de datos!');
        }

        return self::SUCCESS;
    }

    /**
     * Resuelve la ruta del archivo JSON a importar.
     */
    private function resolveFilePath(): string
    {
        $custom = $this->option('file');
        if (! empty($custom)) {
            return base_path($custom);
        }

        // 1) storage/app/productos.json
        $defaultStorage = storage_path('app/productos.json');
        if (file_exists($defaultStorage)) {
            return $defaultStorage;
        }

        // 2) tuc-scraper/productos.json
        $scraperOutput = base_path('tuc-scraper/productos.json');
        if (file_exists($scraperOutput)) {
            return $scraperOutput;
        }

        // 3) tuc-scraper/productos_test.json (si se ejecutó modo test)
        $scraperTest = base_path('tuc-scraper/productos_test.json');
        if (file_exists($scraperTest)) {
            return $scraperTest;
        }

        return $defaultStorage;
    }
}

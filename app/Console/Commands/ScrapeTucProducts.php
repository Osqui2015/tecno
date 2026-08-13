<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Services\TucScraperService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Scraper de productos desde tustecnologiastuc.com
 *
 * Uso:
 *   php artisan tuc:scrape                      Scrapea todas las páginas (delay 1s)
 *   php artisan tuc:scrape --pages=2            Solo las primeras 2 páginas
 *   php artisan tuc:scrape --delay=2            2 segundos entre requests
 *   php artisan tuc:scrape --dry-run           No guarda, solo muestra qué traería
 *   php artisan tuc:scrape --fresh              Vacía productos tuc antes
 *   php artisan tuc:scrape --category=hogar     Solo productos de esa categoría
 */
class ScrapeTucProducts extends Command
{
    protected $signature = 'tuc:scrape
        {--pages=          : Limitar cantidad de páginas a scrapear (default: todas)}
        {--delay=1         : Segundos entre cada request (default: 1)}
        {--dry-run         : Simular sin guardar en la base de datos}
        {--fresh           : Vaciar productos de Tuc antes de empezar}
        {--category=       : Filtrar productos por nombre de categoría externa}
        {--no-hide-missing : NO ocultar productos que no aparezcan en el scraping}';

    protected $description = 'Scrapea productos desde tustecnologiastuc.com y los guarda/actualiza en la DB';

    private TucScraperService $scraper;

    public function handle(TucScraperService $scraper): int
    {
        $this->scraper = $scraper;

        $this->newLine();
        $this->info('╔══════════════════════════════════════════╗');
        $this->info('║      TUSTECNOLOGÍATUC — SCRAPER         ║');
        $this->info('╚══════════════════════════════════════════╝');
        $this->newLine();

        $maxPages = $this->option('pages') !== null && $this->option('pages') !== ''
            ? (int) $this->option('pages')
            : null;
        $delay = (int) $this->option('delay');
        $dryRun = (bool) $this->option('dry-run');
        $fresh = (bool) $this->option('fresh');
        $categoryFilter = $this->option('category');
        $hideMissing = ! $this->option('no-hide-missing');

        if ($maxPages !== null) {
            $this->warn("⚠️  Limitando a {$maxPages} página(s)");
        }

        if ($dryRun) {
            $this->warn('🧪 MODO DRY-RUN: no se guardará nada en la DB');
        }

        if ($fresh && ! $dryRun) {
            if (! $this->confirm('¿Borrar TODOS los productos con origin=tuc antes de empezar?', false)) {
                $this->info('Operación cancelada por el usuario.');
                return self::SUCCESS;
            }
            $this->warn('🗑️  Vaciando productos Tuc previos...');
            $deleted = Product::where('origin', 'tuc')->delete();
            $this->info("   Eliminados: {$deleted} productos");
        }

        // Snapshot de external_ids existentes (solo Tuc) para detectar "missing" después
        $existingExternalIds = Product::where('origin', 'tuc')
            ->pluck('external_id')
            ->toArray();
        $this->info("📦 Productos Tuc existentes: " . count($existingExternalIds));

        $this->newLine();
        $this->info('🚀 Iniciando scraping...');
        $startTime = microtime(true);

        // ───── Progress bar para el scraping ─────
        $progressBar = null;
        $currentPage = 1;

        $onProgress = function (array $product, int $page, int $current, ?int $estimatedTotal) use (&$progressBar, &$currentPage): void {
            // Crear el bar la primera vez que sabemos el total
            if ($progressBar === null) {
                if ($estimatedTotal !== null && $estimatedTotal > 0) {
                    $progressBar = $this->output->createProgressBar($estimatedTotal);
                } else {
                    // Modo "indeterminado" (barra infinita) si no pudimos leer el total
                    $progressBar = $this->output->createProgressBar();
                    $progressBar->setMaxSteps(0);
                }
                $progressBar->setFormat(
                    "   Página %scrape_page% | %current%/%max% productos | %percent:3s%% | ETA: %estimated:-6s% | %elapsed:6s%\n"
                    . '   [%bar%]'
                );
                $progressBar->setMessage((string) $page, 'scrape_page');
                $progressBar->start();
            }

            if ($page !== $currentPage) {
                $currentPage = $page;
                $progressBar->setMessage((string) $page, 'scrape_page');
            }
            $progressBar->advance();
        };

        // Scrapeo
        try {
            $result = $this->scraper->scrape($maxPages, $delay, $onProgress);
        } catch (\Throwable $e) {
            if ($progressBar !== null) {
                $progressBar->finish();
                $this->newLine();
            }
            $this->error('❌ Error fatal durante el scraping:');
            $this->error('   ' . $e->getMessage());
            Log::error('TucScraper fatal', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }

        if ($progressBar !== null) {
            $progressBar->finish();
            $this->newLine(2);
        }

        $allProducts = $result['products'];
        $errors = $result['errors'];

        // Deduplicar productos por external_id para evitar conflictos de clave única en la base de datos
        $uniqueProducts = [];
        foreach ($allProducts as $p) {
            if (isset($p['external_id'])) {
                $uniqueProducts[$p['external_id']] = $p;
            }
        }
        $allProducts = array_values($uniqueProducts);

        // Filtrar por categoría si se pidió
        if ($categoryFilter) {
            $needle = Str::lower($categoryFilter);
            $before = count($allProducts);
            $allProducts = array_filter($allProducts, function (array $p) use ($needle) {
                foreach ($p['categories_external'] ?? [] as $cat) {
                    if (Str::contains(Str::lower($cat), $needle)) {
                        return true;
                    }
                }
                return false;
            });
            $allProducts = array_values($allProducts);
            $this->info("🔎 Filtro categoría='{$categoryFilter}': {$before} → " . count($allProducts));
        }

        if ($dryRun) {
            $this->showDryRunReport($allProducts, count($errors), microtime(true) - $startTime);
            return self::SUCCESS;
        }

        // Guardado
        $stats = $this->saveProducts($allProducts);

        // Detección de productos faltantes
        $foundIds = $stats['seen_ids'];
        $missingIds = array_diff($existingExternalIds, $foundIds);
        $hidden = 0;

        if ($hideMissing && $maxPages === null && ! empty($missingIds)) {
            $this->newLine();
            $this->info('🔍 Detectando productos que no aparecieron en el scraping...');
            $hidden = Product::whereIn('external_id', $missingIds)
                ->where('origin', 'tuc')
                ->update([
                    'active' => false,
                    'stock' => 0,
                    'missing_since' => now(),
                ]);
            $this->info("   ❌ Productos ocultos (ya no existen en el origen): {$hidden}");
        } elseif ($hideMissing && $maxPages !== null) {
            $this->newLine();
            $this->warn("⏭️  Con --pages={$maxPages} el scraping fue parcial: NO se ocultaron productos faltantes");
            $this->line('   La limpieza de faltantes solo corre cuando se scrapea el catálogo completo.');
        } elseif (! $hideMissing) {
            $this->warn('⏭️  --no-hide-missing: NO se ocultaron productos faltantes');
        }

        // Marcar last_seen_at y activar todos los scrapeados (solo Tuc)
        if (! empty($foundIds)) {
            Product::whereIn('external_id', $foundIds)
                ->where('origin', 'tuc')
                ->update([
                    'active' => true,
                    'last_seen_at' => now(),
                    'missing_since' => null,
                ]);
        }

        $elapsed = round(microtime(true) - $startTime, 2);

        $this->newLine();
        $this->info('╔══════════════════════════════════════════╗');
        $this->info('║          ✅  REPORTE FINAL               ║');
        $this->info('╚══════════════════════════════════════════╝');
        $this->newLine();

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['⏱️  Tiempo total', "{$elapsed}s"],
                ['📄 Páginas scrapeadas', $result['stats']['pages']],
                ['🔍 Productos extraídos', count($allProducts)],
                ['💾 Nuevos creados', $stats['created']],
                ['🔄 Actualizados', $stats['updated']],
                ['❌ Sin precio (ocultos)', $stats['no_price']],
                ['🙈 Productos marcados con stock=0', $stats['zero_stock']],
                ['🙈 Productos ocultos (ya no existen)', $hidden],
                ['📁 Categorías nuevas', $stats['new_categories']],
                ['🏷️  Origen', 'TusTec-Tuc'],
                ['⚠️  Errores', count($errors)],
            ]
        );

        if (! empty($errors)) {
            $this->newLine();
            $this->warn('⚠️  Errores detectados:');
            foreach (array_slice($errors, 0, 5) as $err) {
                $this->line('   • ' . $err);
            }
            if (count($errors) > 5) {
                $this->line('   ... y ' . (count($errors) - 5) . ' más');
            }
        }

        $this->newLine();
        $this->info('🎉 Comando finalizado.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @return array{created:int, updated:int, no_price:int, zero_stock:int, new_categories:int, seen_ids:array<int,string>}
     */
    private function saveProducts(array $products): array
    {
        $stats = [
            'created' => 0,
            'updated' => 0,
            'no_price' => 0,
            'zero_stock' => 0,
            'new_categories' => 0,
            'seen_ids' => [],
        ];

        if (empty($products)) {
            return $stats;
        }

        $this->newLine();
        $this->info("💾 Guardando " . count($products) . " productos...");

        $progressBar = $this->output->createProgressBar(count($products));
        $progressBar->setFormat('   %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%');
        $progressBar->start();

        // Cache de categorías para evitar hits a DB en cada producto
        $categoryCache = Category::pluck('id', 'name')->toArray();
        $beforeCategories = count($categoryCache);

        // Pre-cache de IDs existentes (solo Tuc)
        $existingByExt = Product::whereIn('external_id', array_column($products, 'external_id'))
            ->where('origin', 'tuc')
            ->pluck('id', 'external_id')
            ->toArray();

        // Desactivar Scout/TNTSearch durante el guardado masivo para evitar
        // errores de "unable to open database file" en hosting compartidos.
        // Luego se reindexa manualmente con `php artisan scout:import`.
        $originalScoutDriver = config('scout.driver');
        config(['scout.driver' => null]);

        DB::beginTransaction();

        try {
            foreach ($products as $p) {
                $progressBar->advance();
                $stats['seen_ids'][] = $p['external_id'];

                // Productos sin precio: los ocultamos
                if ($p['list_price'] === null && $p['cash_price'] === null) {
                    $stats['no_price']++;
                    continue;
                }

                // Stock 0 o negativo: marcar pero igual guardar el producto
                $isOutOfStock = $p['stock'] !== null && $p['stock'] <= 0;

                // Resolver categoría principal (la primera del array externo)
                $categoryId = null;
                if (! empty($p['categories_external'])) {
                    $firstCat = $p['categories_external'][0] ?? null;
                    if ($firstCat) {
                        if (! isset($categoryCache[$firstCat])) {
                            $catSlug = Str::slug($firstCat);
                            $cat = Category::where('slug', $catSlug)->first();
                            if (! $cat) {
                                $cat = Category::create([
                                    'name'       => $firstCat,
                                    'slug'       => $catSlug,
                                    'description' => 'Importado de TustecnologiaTuc',
                                ]);
                            }
                            $categoryCache[$firstCat] = $cat->id;
                        }
                        $categoryId = $categoryCache[$firstCat];
                    }

                }

                // Si no hay categoría, asignar una "Importados" genérica
                if (! $categoryId) {
                    $categoryId = Category::firstOrCreate(
                        ['slug' => 'importados-tuc'],
                        ['name' => 'Importados Tuc', 'description' => 'Productos importados de TustecnologiaTuc']
                    )->id;
                }

                $payload = [
                    'origin'              => 'tuc',
                    'name'                => $p['name'],
                    'image'               => $p['image'],
                    'sku'                 => $p['sku'],
                    'stock'               => $isOutOfStock ? 0 : (int) $p['stock'],
                    'list_price'          => $p['list_price'],
                    'cash_price'          => $p['cash_price'],
                    'price'               => $p['price'] ?? ($p['cash_price'] ?? $p['list_price']),
                    'brand'               => $p['brand'],
                    'source_url'          => $p['source_url'],
                    'categories_external' => $p['categories_external'],
                    'category_id'         => $categoryId,
                    'active'              => ! $isOutOfStock,
                    'last_seen_at'        => now(),
                    'missing_since'       => null,
                ];

                if (isset($existingByExt[$p['external_id']])) {
                    $product = Product::find($existingByExt[$p['external_id']]);
                    if ($product) {
                        $product->fill($payload)->save();
                    }
                    $stats['updated']++;
                } else {
                    Product::create(array_merge(['external_id' => $p['external_id']], $payload));
                    $stats['created']++;
                }

                if ($isOutOfStock) {
                    $stats['zero_stock']++;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            // Restaurar driver de Scout incluso si falla
            config(['scout.driver' => $originalScoutDriver]);
            $this->error('❌ Error guardando productos: ' . $e->getMessage());
            Log::error('TucScraper save error', ['error' => $e->getMessage()]);
            throw $e;
        }

        // Restaurar driver de Scout
        config(['scout.driver' => $originalScoutDriver]);

        $progressBar->finish();
        $this->newLine();

        $this->warn('⚠️  Scout deshabilitado durante el scrape. Reindexá con:');
        $this->line('    php artisan scout:import "App\\Models\\Product"');

        $afterCategories = count($categoryCache);
        $stats['new_categories'] = max(0, $afterCategories - $beforeCategories);

        return $stats;
    }

    /**
     * Reporte del modo dry-run
     */
    private function showDryRunReport(array $products, int $errors, float $elapsed): void
    {
        $this->newLine();
        $this->info('🔮 DRY-RUN — no se guardó nada en la DB');
        $this->newLine();

        if (empty($products)) {
            $this->warn('No se extrajo ningún producto.');
            return;
        }

        $catCounts = [];
        $brandCounts = [];
        $withImage = 0;
        $withPrice = 0;
        $withStock = 0;

        foreach ($products as $p) {
            foreach ($p['categories_external'] ?? [] as $c) {
                $catCounts[$c] = ($catCounts[$c] ?? 0) + 1;
            }
            if ($p['brand']) {
                $brandCounts[$p['brand']] = ($brandCounts[$p['brand']] ?? 0) + 1;
            }
            if ($p['image']) {
                $withImage++;
            }
            if ($p['list_price'] || $p['cash_price']) {
                $withPrice++;
            }
            if ($p['stock'] !== null) {
                $withStock++;
            }
        }

        arsort($catCounts);
        arsort($brandCounts);

        $this->info("📊 " . ($this->option('pages') ?: 'todas') . " páginas procesadas, " . count($products) . " productos");
        $this->info("⏱️  {$elapsed}s");
        $this->newLine();

        $this->info('🏷️  Top 10 categorías externas:');
        $i = 0;
        foreach ($catCounts as $cat => $count) {
            if ($i++ >= 10) {
                break;
            }
            $this->line("   {$cat}: {$count}");
        }
        $this->newLine();

        $this->info('🏷️  Marcas detectadas: ' . count($brandCounts));
        foreach ($brandCounts as $brand => $count) {
            $this->line("   {$brand}: {$count}");
        }
        $this->newLine();

        $this->info('✅ Cobertura:');
        $this->line('   • Con imagen: ' . $withImage . '/' . count($products));
        $this->line('   • Con precio: ' . $withPrice . '/' . count($products));
        $this->line('   • Con stock:  ' . $withStock . '/' . count($products));
        $this->line('   • Con marca:  ' . count($brandCounts) . ' marcas distintas');

        if ($errors > 0) {
            $this->newLine();
            $this->warn("⚠️  {$errors} error(es) durante el scraping");
        }
    }
}

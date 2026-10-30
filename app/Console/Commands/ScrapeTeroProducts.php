<?php

namespace App\Console\Commands;

use App\Jobs\ScrapeProductsJob;
use App\Models\Category;
use App\Models\Product;
use App\Services\TeroScraperService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Scraper de productos desde teromatero.com.ar (Tiendanube)
 *
 * Uso:
 *   php artisan tero:scrape                     Scrapea todas las páginas (delay 1s)
 *   php artisan tero:scrape --pages=2           Solo las primeras 2 páginas
 *   php artisan tero:scrape --delay=2           2 segundos entre requests
 *   php artisan tero:scrape --dry-run           No guarda, solo muestra qué traería
 *   php artisan tero:scrape --fresh             Vacía productos tero antes
 *   php artisan tero:scrape --no-hide-missing   No oculta productos que no aparezcan
 *   php artisan tero:scrape --queue             Despacha como Job en background
 *   php artisan tero:scrape --detail            Después del listado, scrapea la página
 *                                              de detalle de cada producto para obtener
 *                                              stock real, categoría real, descripción
 *                                              y galería de imágenes.
 *
 * Sobre el detail-pass (--detail):
 *  - Hace 1 request extra por producto (535 requests para todo el catálogo).
 *  - Stock: solo se actualiza si BAJA (no sube), para no pisar ventas pendientes.
 *  - Categoría real: viene de los breadcrumbs de la página de detalle.
 *  - Galería: completa, no solo la imagen principal.
 *  - Descripción: solo si está en el JSON-LD (Tiendanube a veces no la expone).
 */
class ScrapeTeroProducts extends Command
{
    protected $signature = 'tero:scrape
        {--pages=          : Limitar cantidad de páginas a scrapear (default: todas)}
        {--delay=1         : Segundos entre cada request (default: 1)}
        {--detail-delay=0.3 : Segundos entre requests de detalle (default: 0.3)}
        {--dry-run         : Simular sin guardar en la base de datos}
        {--fresh           : Vaciar productos de origen Tero antes de empezar}
        {--no-hide-missing : NO ocultar productos que no aparezcan en el scraping}
        {--detail          : Scrapea también la página de detalle de cada producto}
        {--queue           : Despachar el scrape como Job en background}';

    protected $description = 'Scrapea productos desde teromatero.com.ar (Tiendanube) y los guarda/actualiza en la DB';

    private TeroScraperService $scraper;

    public function handle(TeroScraperService $scraper): int
    {
        $this->scraper = $scraper;

        // Marcamos en el container que estamos scrapeando para que el observer
        // del modelo no registre cambios automáticos de stock.
        app()->instance('scrape_in_progress', true);
        app()->instance('scrape_origin', 'tero');

        // Modo queue
        if ($this->option('queue')) {
            if ($this->option('dry-run')) {
                $this->error('❌ --dry-run no es compatible con --queue');
                return self::FAILURE;
            }
            $pages = $this->option('pages') !== null && $this->option('pages') !== ''
                ? (int) $this->option('pages')
                : null;
            ScrapeProductsJob::dispatch(
                origin: 'tero',
                scraperClass: TeroScraperService::class,
                maxPages: $pages,
                delaySeconds: (int) $this->option('delay'),
                fresh: (bool) $this->option('fresh'),
                hideMissing: ! $this->option('no-hide-missing'),
            );
            $this->info('✅ Job despachado a la cola. Procesalo con:');
            $this->line('   php artisan queue:work --queue=default');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('╔══════════════════════════════════════════╗');
        $this->info('║     TEROMATERO — SCRAPER DE PRODUCTOS    ║');
        $this->info('║       (Plataforma: Tiendanube)           ║');
        $this->info('╚══════════════════════════════════════════╝');
        $this->newLine();

        $maxPages = $this->option('pages') !== null && $this->option('pages') !== ''
            ? (int) $this->option('pages')
            : null;
        $delay = (int) $this->option('delay');
        $detailDelay = (float) $this->option('detail-delay');
        $dryRun = (bool) $this->option('dry-run');
        $fresh = (bool) $this->option('fresh');
        $hideMissing = ! $this->option('no-hide-missing');
        $doDetail = (bool) $this->option('detail');

        if ($maxPages !== null) {
            $this->warn("⚠️  Limitando a {$maxPages} página(s)");
        }
        if ($dryRun) {
            $this->warn('🧪 MODO DRY-RUN: no se guardará nada en la DB');
        }
        if ($doDetail) {
            $this->info('🔍 --detail activado: se va a scrapear el detalle de cada producto');
            $this->info("   (delay entre detalles: {$detailDelay}s)");
        }

        if ($fresh && ! $dryRun) {
            if (! $this->confirm('¿Borrar TODOS los productos con origin=tero antes de empezar?', false)) {
                $this->info('Operación cancelada por el usuario.');
                return self::SUCCESS;
            }
            $this->warn('🗑️  Vaciando productos Tero previos...');
            $deleted = Product::where('origin', 'tero')->delete();
            $this->info("   Eliminados: {$deleted} productos");
        }

        // Snapshot de external_ids existentes (solo Tero) para detectar "missing" después
        $existingExternalIds = Product::where('origin', 'tero')
            ->pluck('external_id')
            ->toArray();
        $this->info('📦 Productos Tero existentes: ' . count($existingExternalIds));

        $this->newLine();
        $this->info('🚀 Iniciando scraping...');
        $startTime = microtime(true);

        // Progress bar
        $progressBar = null;
        $currentPage = 1;
        $onProgress = function (array $product, int $page, int $current, ?int $estimatedTotal) use (&$progressBar, &$currentPage): void {
            if ($progressBar === null) {
                if ($estimatedTotal !== null && $estimatedTotal > 0) {
                    $progressBar = $this->output->createProgressBar($estimatedTotal);
                } else {
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

        try {
            $result = $this->scraper->scrape($maxPages, $delay, $onProgress);
        } catch (\Throwable $e) {
            if ($progressBar !== null) {
                $progressBar->finish();
                $this->newLine();
            }
            $this->error('❌ Error fatal durante el scraping:');
            $this->error('   ' . $e->getMessage());
            Log::error('TeroScraper fatal', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }

        if ($progressBar !== null) {
            $progressBar->finish();
            $this->newLine(2);
        }

        $allProducts = $result['products'];
        $errors = $result['errors'];

        // Deduplicar por external_id
        $uniqueProducts = [];
        foreach ($allProducts as $p) {
            if (isset($p['external_id'])) {
                $uniqueProducts[$p['external_id']] = $p;
            }
        }
        $allProducts = array_values($uniqueProducts);

        $stats = [
            'created' => 0, 'updated' => 0, 'no_price' => 0,
            'zero_stock' => 0, 'new_categories' => 0, 'seen_ids' => [],
        ];
        if (! $dryRun) {
            $stats = $this->saveProducts($allProducts);
        }

        // Detección de productos faltantes
        $foundIds = $stats['seen_ids'];
        $missingIds = array_diff($existingExternalIds, $foundIds);
        $hidden = 0;

        if (! $dryRun) {
            if ($hideMissing && $maxPages === null && ! empty($missingIds)) {
                $this->newLine();
                $this->info('🔍 Detectando productos que no aparecieron en el scraping...');
                $hidden = Product::whereIn('external_id', $missingIds)
                    ->where('origin', 'tero')
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

            if (! empty($foundIds)) {
                Product::whereIn('external_id', $foundIds)
                    ->where('origin', 'tero')
                    ->update([
                        'active' => true,
                        'last_seen_at' => now(),
                        'missing_since' => null,
                    ]);
            }
        }

        // ============================================================
        //  Detail-pass: stock real, categoría, descripción, galería
        // ============================================================
        $detailStats = [
            'enriched' => 0,
            'stock_lowered' => 0,
            'category_updated' => 0,
            'gallery_updated' => 0,
            'description_updated' => 0,
            'errors' => 0,
        ];

        if ($doDetail && ! empty($allProducts)) {
            $detailStats = $this->enrichWithDetail($allProducts, $detailDelay, $dryRun);
        }

        $elapsed = round(microtime(true) - $startTime, 2);

        $this->newLine();
        $this->info('╔══════════════════════════════════════════╗');
        $this->info('║          ✅  REPORTE FINAL               ║');
        $this->info('╚══════════════════════════════════════════╝');
        $this->newLine();

        $rows = [
            ['⏱️  Tiempo total', "{$elapsed}s"],
            ['📄 Páginas scrapeadas', $result['stats']['pages']],
            ['🔍 Productos extraídos', count($allProducts)],
            ['💾 Nuevos creados', $stats['created']],
            ['🔄 Actualizados', $stats['updated']],
            ['❌ Sin precio (ocultos)', $stats['no_price']],
            ['🙈 Productos con stock=0', $stats['zero_stock']],
            ['🙈 Productos ocultos (ya no existen)', $hidden],
            ['📁 Categorías nuevas', $stats['new_categories']],
            ['🏷️  Origen', 'Teromatero'],
            ['⚠️  Errores', count($errors)],
        ];
        if ($doDetail) {
            $rows[] = ['────────────────────', '────────────────'];
            $rows[] = ['🔍 Productos enriquecidos (detail-pass)', $detailStats['enriched']];
            $rows[] = ['📉 Stock bajado', $detailStats['stock_lowered']];
            $rows[] = ['📁 Categorías actualizadas', $detailStats['category_updated']];
            $rows[] = ['🖼️  Galerías actualizadas', $detailStats['gallery_updated']];
            $rows[] = ['📝 Descripciones actualizadas', $detailStats['description_updated']];
            $rows[] = ['❌ Errores en detail', $detailStats['errors']];
        }
        $this->table(['Métrica', 'Valor'], $rows);

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

        if ($dryRun) {
            $this->showDryRunReport($allProducts, count($errors), microtime(true) - $startTime, $detailStats);
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
        $this->info('💾 Guardando ' . count($products) . ' productos...');

        $progressBar = $this->output->createProgressBar(count($products));
        $progressBar->setFormat('   %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%');
        $progressBar->start();

        $categoryCache = Category::pluck('id', 'name')->toArray();
        $beforeCategories = count($categoryCache);

        $existingByExt = Product::whereIn('external_id', array_column($products, 'external_id'))
            ->where('origin', 'tero')
            ->pluck('id', 'external_id')
            ->toArray();

        // Desactivar Scout/TNTSearch durante el guardado masivo
        $originalScoutDriver = config('scout.driver');
        config(['scout.driver' => null]);

        DB::beginTransaction();

        try {
            foreach ($products as $p) {
                $progressBar->advance();
                $stats['seen_ids'][] = $p['external_id'];

                if ($p['list_price'] === null && $p['cash_price'] === null) {
                    $stats['no_price']++;
                    continue;
                }

                // Tiendanube no muestra stock en la card, asumimos stock=1 si está activo
                $stock = $p['stock'] !== null ? (int) $p['stock'] : 1;
                $isOutOfStock = $stock <= 0;

                // Categoría: si hay heurística, usar; sino, "Importados Tero"
                $categoryId = null;
                if (! empty($p['categories_external'])) {
                    $firstCat = $p['categories_external'][0] ?? null;
                    if ($firstCat) {
                        if (! isset($categoryCache[$firstCat])) {
                            $catSlug = Str::slug($firstCat);
                            $cat = Category::where('slug', $catSlug)->first();
                            if (! $cat) {
                                $cat = Category::create([
                                    'name'        => $firstCat,
                                    'slug'        => $catSlug,
                                    'description' => 'Importado de Teromatero',
                                ]);
                            }
                            $categoryCache[$firstCat] = $cat->id;
                        }
                        $categoryId = $categoryCache[$firstCat];
                    }
                }
                if (! $categoryId) {
                    $categoryId = Category::firstOrCreate(
                        ['slug' => 'importados-tero'],
                        ['name' => 'Importados Tero', 'description' => 'Productos importados de Teromatero']
                    )->id;
                }

                $payload = [
                    'origin'              => 'tero',
                    'name'                => $p['name'],
                    'image'               => $p['image'],
                    'sku'                 => $p['sku'],
                    'stock'               => $isOutOfStock ? 0 : $stock,
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
                        $stockBefore = (int) $product->stock;
                        $product->fill($payload)->save();
                        $stockAfter = (int) $product->fresh()->stock;
                        if ($stockBefore !== $stockAfter) {
                            $product->recordStockChange('scraper', 'tero:scrape');
                        }
                    }
                    $stats['updated']++;
                } else {
                    $newProduct = Product::create(array_merge(['external_id' => $p['external_id']], $payload));
                    $newProduct->recordStockChange('scraper', 'tero:scrape:new');
                    $stats['created']++;
                }

                if ($isOutOfStock) {
                    $stats['zero_stock']++;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            config(['scout.driver' => $originalScoutDriver]);
            $this->error('❌ Error guardando productos: ' . $e->getMessage());
            Log::error('TeroScraper save error', ['error' => $e->getMessage()]);
            throw $e;
        }

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
    private function showDryRunReport(array $products, int $errors, float $elapsed, array $detailStats = []): void
    {
        $this->newLine();
        $this->info('🔮 DRY-RUN — no se guardó nada en la DB');
        $this->newLine();

        if (empty($products)) {
            $this->warn('No se extrajo ningún producto.');
            return;
        }

        $catCounts = [];
        $withImage = 0;
        $withPrice = 0;
        $withCash = 0;
        $withDiscount = 0;

        foreach ($products as $p) {
            foreach ($p['categories_external'] ?? [] as $c) {
                $catCounts[$c] = ($catCounts[$c] ?? 0) + 1;
            }
            if ($p['image']) $withImage++;
            if ($p['list_price'] || $p['cash_price']) $withPrice++;
            if ($p['cash_price'] !== null) {
                $withCash++;
                $withDiscount++;
            }
        }

        arsort($catCounts);

        $this->info('📊 ' . count($products) . " productos extraídos");
        $this->info("⏱️  {$elapsed}s");
        $this->info("🖼️  Con imagen: {$withImage}/" . count($products));
        $this->info("💰 Con precio: {$withPrice}/" . count($products));
        $this->info("💵 Con descuento por transferencia: {$withCash}/" . count($products));
        $this->newLine();

        $this->info('🏷️  Categorías detectadas:');
        $i = 0;
        foreach ($catCounts as $cat => $count) {
            if ($i++ >= 10) break;
            $this->line("   {$cat}: {$count}");
        }

        if ($withDiscount > 0) {
            $this->newLine();
            $this->info('💵 Muestra de productos con descuento:');
            $i = 0;
            foreach ($products as $p) {
                if ($p['cash_price'] === null) continue;
                $this->line(sprintf(
                    '   • %s | lista $%s → transferencia $%s',
                    $p['name'],
                    number_format($p['list_price'], 0, ',', '.'),
                    number_format($p['cash_price'], 0, ',', '.')
                ));
                if (++$i >= 5) break;
            }
        }

        if (! empty($detailStats)) {
            $this->newLine();
            $this->info('🔍 Detail-pass (simulado, no se guardó):');
            $this->line("   • {$detailStats['enriched']} productos a enriquecer");
            $this->line("   • {$detailStats['stock_lowered']} stocks a bajar");
            $this->line("   • {$detailStats['category_updated']} categorías a actualizar");
            $this->line("   • {$detailStats['gallery_updated']} galerías a actualizar");
            $this->line("   • {$detailStats['description_updated']} descripciones a agregar");
            if ($detailStats['errors'] > 0) {
                $this->warn("   • ❌ {$detailStats['errors']} errores (ver log)");
            }
        }
    }

    /**
     * Detail-pass: scrapea la página individual de cada producto y actualiza:
     *  - stock (SOLO si el nuevo stock es MENOR al actual)
     *  - categoría real (desde los breadcrumbs)
     *  - galería de imágenes
     *  - descripción (si está disponible)
     *
     * @param  array<int, array<string, mixed>>  $products  productos scrapeados del listado
     * @param  float  $detailDelay  segundos entre cada request de detalle
     * @param  bool   $dryRun       si es true, no guarda nada en la DB (solo simula)
     * @return array<string, int>  estadísticas del detail-pass
     */
    private function enrichWithDetail(array $products, float $detailDelay, bool $dryRun = false): array
    {
        $stats = [
            'enriched' => 0,
            'stock_lowered' => 0,
            'category_updated' => 0,
            'gallery_updated' => 0,
            'description_updated' => 0,
            'errors' => 0,
        ];

        $this->newLine();
        $this->info('🔍 Iniciando detail-pass de ' . count($products) . ' productos...'
            . ($dryRun ? ' (DRY-RUN: no se guardará nada)' : ''));
        $this->newLine();

        $progressBar = $this->output->createProgressBar(count($products));
        $progressBar->setFormat('   %current%/%max% [%bar%] %percent:3s%% ETA: %estimated:-6s% %elapsed:6s%');
        $progressBar->start();

        // Cache de categorías
        $categoryCache = Category::pluck('id', 'name')->toArray();

        foreach ($products as $p) {
            $progressBar->advance();
            $sourceUrl = $p['source_url'] ?? null;
            $externalId = $p['external_id'] ?? null;
            if (! $sourceUrl || ! $externalId) {
                continue;
            }

            // En dry-run, el producto puede NO existir en la DB todavía.
            // Si no existe, simulamos con los datos del listado.
            $product = Product::where('external_id', $externalId)
                ->where('origin', 'tero')
                ->first();

            try {
                $detail = $this->scraper->fetchProductDetail($sourceUrl);
            } catch (\Throwable $e) {
                $stats['errors']++;
                Log::warning('Tero detail-pass error', [
                    'external_id' => $externalId,
                    'url' => $sourceUrl,
                    'error' => $e->getMessage(),
                ]);
                if ($detailDelay > 0) usleep((int) ($detailDelay * 1_000_000));
                continue;
            }

            $updates = [];

            // 1) Stock: lógica de actualización
            //    - Si el producto NO existe en la DB: usar el stock del sitio directamente.
            //    - Si existe con stock=1 (placeholder del listado): actualizar al real
            //      (porque el stock del sitio es más preciso).
            //    - Si existe con stock>1 (probablemente ajustado por una venta):
            //      SOLO bajar si el stock del sitio es MENOR (no pisar ventas pendientes).
            if ($detail['stock'] !== null) {
                $newStock = (int) $detail['stock'];
                $currentStock = $product ? (int) $product->stock : null;

                if ($currentStock === null || $currentStock <= 1) {
                    // Producto nuevo o con stock placeholder → usar el real
                    $updates['stock'] = $newStock;
                } elseif ($newStock < $currentStock) {
                    // Stock del sitio es menor → bajar
                    $updates['stock'] = $newStock;
                    $stats['stock_lowered']++;
                }
                // Si newStock >= currentStock Y currentStock > 1, no tocamos (hay ventas)

                if ($newStock === 0 && (! $product || $product->active)) {
                    $updates['active'] = false;
                }
            }

            // 2) Categoría real
            if (! empty($detail['category_path']) && ! $dryRun) {
                $firstCat = $detail['category_path'][0];
                if (! isset($categoryCache[$firstCat])) {
                    $catSlug = Str::slug($firstCat);
                    $cat = Category::where('slug', $catSlug)->first();
                    if (! $cat) {
                        $cat = Category::create([
                            'name'        => $firstCat,
                            'slug'        => $catSlug,
                            'description' => 'Importado de Teromatero',
                        ]);
                    }
                    $categoryCache[$firstCat] = $cat->id;
                }
                $updates['category_id'] = $categoryCache[$firstCat];
                $updates['categories_external'] = $detail['category_path'];
                $stats['category_updated']++;
            } elseif (! empty($detail['category_path'])) {
                // En dry-run, solo contamos sin resolver
                $stats['category_updated']++;
            }

            // 3) Galería
            if (! empty($detail['gallery'])) {
                $updates['gallery'] = $detail['gallery'];
                if ((! $product || empty($product->image)) && isset($detail['gallery'][0])) {
                    $updates['image'] = $detail['gallery'][0];
                }
                $stats['gallery_updated']++;
            }

            // 4) Descripción
            //    - Si la nueva descripción existe: actualizamos solo si la actual está vacía
            //    - Si la nueva descripción es null (no hay JSON-LD propio): NO limpiamos
            //      automáticamente, porque podría tener una descripción válida escrita
            //      manualmente. Solo limpiamos si --reset-desc está activo.
            if (! empty($detail['description']) && (! $product || empty($product->description))) {
                $updates['description'] = $detail['description'];
                $stats['description_updated']++;
            }

            // 5) SKU
            if (! empty($detail['sku']) && (! $product || empty($product->sku))) {
                $updates['sku'] = $detail['sku'];
            }

            if (! empty($updates) && ! $dryRun && $product) {
                // ⚠️ Usamos forceFill (no fill) porque Eloquent no detecta
                // cambios en columnas JSON casteadas a array, lo cual hacía
                // que la galería y categories_external no se persistieran.
                $updates['last_updated_at'] = now();
                $product->forceFill($updates);
                $product->save();
                $stats['enriched']++;
            } elseif (! empty($updates)) {
                // En dry-run, contamos como "se hubiera enriquecido" pero no guardamos
                $stats['enriched']++;
            }

            if ($detailDelay > 0) {
                usleep((int) ($detailDelay * 1_000_000));
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        if (! $dryRun) {
            $this->info("✅ Detail-pass completo:");
            $this->line("   • {$stats['enriched']} productos enriquecidos");
            $this->line("   • {$stats['stock_lowered']} stocks bajados");
            $this->line("   • {$stats['category_updated']} categorías actualizadas");
            $this->line("   • {$stats['gallery_updated']} galerías actualizadas");
            if ($stats['errors'] > 0) {
                $this->warn("   • ❌ {$stats['errors']} errores (ver log)");
            }
        }

        return $stats;
    }
}

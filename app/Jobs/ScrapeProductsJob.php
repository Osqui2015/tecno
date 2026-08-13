<?php

namespace App\Jobs;

use App\Models\Category;
use App\Models\Product;
use App\Services\BaseWoodmartScraper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Job que ejecuta el scraping de un proveedor y guarda los productos.
 *
 * Se despacha desde los comandos daz:scrape / tuc:scrape cuando se usa --queue.
 * También puede dispararse programáticamente (ej. scheduler nocturno).
 *
 * NO muestra progress bar (no tiene acceso a la consola). Loguea a 'scout.log'
 * para que el admin pueda seguir el avance con `php artisan pail` o `tail -f`.
 */
class ScrapeProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Tamaño del chunk para reindexar Scout. */
    public const SCOUT_CHUNK = 200;

    public int $tries = 1; // No reintentar: si falla, que el admin vea el error.
    public int $timeout = 3600; // 1h máximo

    /**
     * @param  string  $origin  'daz' | 'tuc' — usado para loguear.
     * @param  string  $scraperClass  FQCN del scraper concreto.
     */
    public function __construct(
        public readonly string $origin,
        public readonly string $scraperClass,
        public readonly ?int $maxPages = null,
        public readonly int $delaySeconds = 1,
        public readonly bool $fresh = false,
        public readonly bool $hideMissing = true,
    ) {
    }

    public function handle(): void
    {
        Log::info("ScrapeProductsJob: iniciando {$this->origin}");

        // Marcamos en el container que estamos scrapeando para que el observer
        // del modelo no registre cambios automáticos (los registramos nosotros).
        app()->instance('scrape_in_progress', true);

        /** @var BaseWoodmartScraper $scraper */
        $scraper = app($this->scraperClass);

        if ($this->fresh) {
            $deleted = Product::where('origin', $this->origin)->delete();
            Log::info("ScrapeProductsJob: {$deleted} productos borrados (--fresh)");
        }

        $existingExternalIds = Product::where('origin', $this->origin)
            ->pluck('external_id')
            ->toArray();

        $onProgress = function (array $product, int $page, int $current, ?int $estimatedTotal): void {
            Log::info("ScrapeProductsJob: {$this->origin} p{$page} {$current}/{$estimatedTotal}", [
                'external_id' => $product['external_id'] ?? null,
                'name'        => $product['name'] ?? null,
            ]);
        };

        $result = $scraper->scrape($this->maxPages, $this->delaySeconds, $onProgress);
        $allProducts = $result['products'];

        // Deduplicar
        $unique = [];
        foreach ($allProducts as $p) {
            if (isset($p['external_id'])) {
                $unique[$p['external_id']] = $p;
            }
        }
        $allProducts = array_values($unique);

        $stats = $this->saveProducts($allProducts);

        // Detección de faltantes
        $foundIds = $stats['seen_ids'];
        $missingIds = array_diff($existingExternalIds, $foundIds);
        $hidden = 0;
        if ($this->hideMissing && $this->maxPages === null && ! empty($missingIds)) {
            $hidden = Product::whereIn('external_id', $missingIds)
                ->where('origin', $this->origin)
                ->update([
                    'active' => false,
                    'stock' => 0,
                    'missing_since' => now(),
                ]);
        }

        if (! empty($foundIds)) {
            Product::whereIn('external_id', $foundIds)
                ->where('origin', $this->origin)
                ->update([
                    'active' => true,
                    'last_seen_at' => now(),
                    'missing_since' => null,
                ]);
        }

        Log::info("ScrapeProductsJob: {$this->origin} finalizado", [
            'pages'             => $result['stats']['pages'],
            'created'           => $stats['created'],
            'updated'           => $stats['updated'],
            'hidden'            => $hidden,
            'new_categories'    => $stats['new_categories'],
            'errors'            => count($result['errors']),
        ]);
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

        $categoryCache = Category::pluck('id', 'name')->toArray();
        $beforeCategories = count($categoryCache);

        $existingByExt = Product::whereIn('external_id', array_column($products, 'external_id'))
            ->where('origin', $this->origin)
            ->pluck('id', 'external_id')
            ->toArray();

        DB::beginTransaction();
        try {
            foreach ($products as $p) {
                $stats['seen_ids'][] = $p['external_id'];

                if ($p['list_price'] === null && $p['cash_price'] === null) {
                    $stats['no_price']++;
                    continue;
                }

                $isOutOfStock = $p['stock'] !== null && $p['stock'] <= 0;

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
                                    'description' => "Importado de {$this->origin}",
                                ]);
                            }
                            $categoryCache[$firstCat] = $cat->id;
                        }
                        $categoryId = $categoryCache[$firstCat];
                    }
                }

                if (! $categoryId) {
                    $categoryId = Category::firstOrCreate(
                        ['slug' => 'importados'],
                        ['name' => 'Importados', 'description' => "Productos importados de {$this->origin}"]
                    )->id;
                }

                $payload = [
                    'origin'              => $this->origin,
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
                        $stockBefore = (int) $product->stock;
                        $product->fill($payload)->save();
                        $stockAfter = (int) $product->fresh()->stock;
                        if ($stockBefore !== $stockAfter) {
                            $product->recordStockChange('scraper', "{$this->origin}:scrape");
                        }
                    }
                    $stats['updated']++;
                } else {
                    $newProduct = Product::create(array_merge(['external_id' => $p['external_id']], $payload));
                    $newProduct->recordStockChange('scraper', "{$this->origin}:scrape:new");
                    $stats['created']++;
                }

                if ($isOutOfStock) {
                    $stats['zero_stock']++;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("ScrapeProductsJob: error guardando {$this->origin}", ['error' => $e->getMessage()]);
            throw $e;
        }

        $afterCategories = count($categoryCache);
        $stats['new_categories'] = max(0, $afterCategories - $beforeCategories);

        return $stats;
    }
}

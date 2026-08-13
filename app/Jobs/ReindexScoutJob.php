<?php

namespace App\Jobs;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Reindexa Scout con todos los productos activos.
 *
 * Se despacha al final de products:sync --queue para que el catálogo
 * quede buscable después de un scrape en background.
 */
class ReindexScoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 1800;

    public function handle(): void
    {
        $count = Product::query()->where('active', true)->count();
        Log::info("ReindexScoutJob: reindexando {$count} productos activos");

        Artisan::call('scout:flush', ['searchable' => Product::class]);
        Artisan::call('scout:import', ['searchable' => Product::class]);

        Log::info('ReindexScoutJob: finalizado');
    }
}

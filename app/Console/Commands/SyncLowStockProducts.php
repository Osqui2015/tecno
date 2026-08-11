<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

/**
 * Sincroniza el flag `active` según el stock actual:
 *   - stock <  LOW_STOCK_THRESHOLD  →  active = false  (se desactiva)
 *   - stock >= LOW_STOCK_THRESHOLD  →  no se toca (no se reactiva solo)
 *
 * Es seguro correrlo varias veces. Útil para corregir datos legacy
 * o después de importar productos masivamente.
 *
 * Uso:
 *   php artisan products:sync-low-stock
 *   php artisan products:sync-low-stock --dry-run
 *   php artisan products:sync-low-stock --chunk=500
 */
class SyncLowStockProducts extends Command
{
    protected $signature = 'products:sync-low-stock
                            {--dry-run : Solo muestra qué cambiaría, sin escribir en BD}
                            {--chunk=500 : Tamaño del lote al recorrer la tabla}';

    protected $description = 'Desactiva productos con stock menor a 5 (Product::LOW_STOCK_THRESHOLD).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk  = max(1, (int) $this->option('chunk'));
        $threshold = Product::LOW_STOCK_THRESHOLD;

        $this->info("Sincronizando productos con stock < {$threshold} → active = false");
        if ($dryRun) {
            $this->warn('Modo DRY-RUN: no se realizarán cambios.');
        }
        $this->newLine();

        $deactivated = 0;
        $alreadyOk   = 0;
        $total       = 0;

        // Recorremos por ID para evitar OFFSET y ser eficiente en tablas grandes.
        Product::query()
            ->where('stock', '<', $threshold)
            ->where('active', true)
            ->orderBy('id')
            ->chunkById($chunk, function ($products) use ($dryRun, &$deactivated, &$alreadyOk, &$total) {
                foreach ($products as $product) {
                    $total++;
                    if ($dryRun) {
                        $this->line("  · [{$product->id}] {$product->name} (stock={$product->stock}) → inactivo");
                        $deactivated++;
                        continue;
                    }
                    // save() dispara el evento del modelo, que también fuerza active=false,
                    // pero acá lo hacemos explícito para que el contador sea preciso.
                    $product->active = false;
                    if ($product->save()) {
                        $deactivated++;
                    }
                }
            }, 'id');

        // También contamos los que YA están en estado correcto para dar un reporte claro.
        Product::query()
            ->where(function ($q) use ($threshold) {
                $q->where('stock', '>=', $threshold)
                  ->orWhere('active', false);
            })
            ->orderBy('id')
            ->chunkById($chunk, function ($products) use (&$alreadyOk) {
                foreach ($products as $product) {
                    // Solo contamos los que cumplen "stock<5 ⇒ inactivo" y ya están bien.
                    if ((int) $product->stock < Product::LOW_STOCK_THRESHOLD && $product->active === false) {
                        $alreadyOk++;
                    }
                }
            }, 'id');

        $this->newLine();
        $this->info('Resumen:');
        $this->table(
            ['Concepto', 'Cantidad'],
            [
                ['Productos revisados (stock < ' . $threshold . ')',  $total],
                ['Desactivados' . ($dryRun ? ' (simulados)' : ''),     $deactivated],
                ['Ya estaban correctos',                                $alreadyOk],
            ]
        );

        if ($dryRun) {
            $this->warn('DRY-RUN finalizado. Sin cambios en la BD.');
        } else {
            $this->info('Sincronización finalizada.');
        }

        return self::SUCCESS;
    }
}

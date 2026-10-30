<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Comando unificado: scrapea Daz + Tustecnologia + Teromatero y reindexa Scout al final.
 *
 * Uso:
 *   php artisan products:sync                       Scrapea los tres y reindexa
 *   php artisan products:sync --skip-daz            Solo Tustecnologia + Teromatero
 *   php artisan products:sync --skip-tuc            Solo Daz + Teromatero
 *   php artisan products:sync --skip-tero           Solo Daz + Tustecnologia
 *   php artisan products:sync --no-reindex          No reindexar Scout al final
 *   php artisan products:sync --pages=2 --delay=2   Limitar páginas y delay
 *   php artisan products:sync --fresh               Vaciar externos antes
 *   php artisan products:sync --dry-run             Simular sin guardar
 *   php artisan products:sync --detail              Detail-pass después del listado (stock real)
 */
class SyncAllProducts extends Command
{
    protected $signature = 'products:sync
        {--skip-daz          : No scrapear Daz}
        {--skip-tuc          : No scrapear Tustecnologia}
        {--skip-tero         : No scrapear Teromatero}
        {--no-reindex        : No reindexar Scout al finalizar}
        {--pages=            : Limitar cantidad de páginas por scraper}
        {--delay=1           : Segundos entre requests (default: 1)}
        {--detail-delay=0.3  : Segundos entre requests de detalle (default: 0.3)}
        {--dry-run           : Simular sin guardar en la base de datos}
        {--fresh             : Vaciar productos externos antes de empezar}
        {--category=         : Filtrar productos por nombre de categoría externa}
        {--no-hide-missing   : NO ocultar productos que no aparezcan en el scraping}
        {--detail            : Después del listado, scrapear la página de detalle (stock real)}
        {--queue             : Despachar todos los scrapes como Jobs en background}';

    protected $description = 'Scrapea Daz, Tustecnologia y Teromatero, luego reindexa Scout';

    public function handle(): int
    {
        $skipDaz    = (bool) $this->option('skip-daz');
        $skipTuc    = (bool) $this->option('skip-tuc');
        $skipTero   = (bool) $this->option('skip-tero');
        $noReindex  = (bool) $this->option('no-reindex');
        $useQueue   = (bool) $this->option('queue');

        // Si se pasa --queue, despachamos los Jobs y salimos.
        if ($useQueue) {
            if ($this->option('dry-run')) {
                $this->error('❌ --dry-run no es compatible con --queue');
                return self::FAILURE;
            }
            $opts = $this->buildOptions();
            $maxPages    = isset($opts['--pages']) ? (int) $opts['--pages'] : null;
            $delaySec    = isset($opts['--delay']) ? (int) $opts['--delay'] : 1;
            $isFresh     = isset($opts['--fresh']);
            $hideMissing = ! isset($opts['--no-hide-missing']);
            $doDetail    = isset($opts['--detail']);
            $detailDelay = isset($opts['--detail-delay']) ? (float) $opts['--detail-delay'] : 0.3;
            $jobs = [];
            if (! $skipDaz) {
                $jobs[] = \App\Jobs\ScrapeProductsJob::dispatch(
                    'daz',
                    \App\Services\DazScraperService::class,
                    $maxPages,
                    $delaySec,
                    $isFresh,
                    $hideMissing,
                    $doDetail,
                    $detailDelay,
                );
            }
            if (! $skipTuc) {
                $jobs[] = \App\Jobs\ScrapeProductsJob::dispatch(
                    'tuc',
                    \App\Services\TucScraperService::class,
                    $maxPages,
                    $delaySec,
                    $isFresh,
                    $hideMissing,
                    $doDetail,
                    $detailDelay,
                );
            }
            if (! $skipTero) {
                // Tero en modo --queue usa su propio flujo, no este job.
                // --detail en este caso se aplica solo en modo síncrono.
                $jobs[] = \App\Jobs\ScrapeProductsJob::dispatch(
                    'tero',
                    \App\Services\TeroScraperService::class,
                    $maxPages,
                    $delaySec,
                    $isFresh,
                    $hideMissing,
                );
            }
            if (! $noReindex) {
                \App\Jobs\ReindexScoutJob::dispatch();
            }
            $this->info('✅ ' . count($jobs) . ' job(s) despachado(s). Procesalos con:');
            $this->line('   php artisan queue:work --queue=default');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('╔══════════════════════════════════════════╗');
        $this->info('║   SINCRONIZACIÓN TOTAL DE PRODUCTOS     ║');
        $this->info('╚══════════════════════════════════════════╝');
        $this->newLine();

        $start = microtime(true);

        // Contar cuántos scrapers quedan para que el [N/M] sea correcto
        $totalSteps = (int) (! $skipDaz) + (int) (! $skipTuc) + (int) (! $skipTero) + (int) (! $noReindex);
        $currentStep = 0;

        // ====== DAZ ======
        if (! $skipDaz) {
            $currentStep++;
            $this->info("▶  [{$currentStep}/{$totalSteps}] Scrapeando Daz Importadora...");
            $this->newLine();

            $exit = $this->call('daz:scrape', $this->buildOptions());
            if ($exit !== 0) {
                $this->error('❌ Falló el scraper de Daz');
                return $exit;
            }
            $this->newLine();
        } else {
            $this->warn('⏭  Daz omitido (--skip-daz)');
            $this->newLine();
        }

        // ====== TUC ======
        if (! $skipTuc) {
            $currentStep++;
            $this->info("▶  [{$currentStep}/{$totalSteps}] Scrapeando Tustecnologia...");
            $this->newLine();

            $exit = $this->call('tuc:scrape', $this->buildOptions());
            if ($exit !== 0) {
                $this->error('❌ Falló el scraper de Tustecnologia');
                return $exit;
            }
            $this->newLine();
        } else {
            $this->warn('⏭  Tustecnologia omitido (--skip-tuc)');
            $this->newLine();
        }

        // ====== TERO ======
        if (! $skipTero) {
            $currentStep++;
            $this->info("▶  [{$currentStep}/{$totalSteps}] Scrapeando Teromatero...");
            $this->newLine();

            $exit = $this->call('tero:scrape', $this->buildOptions());
            if ($exit !== 0) {
                $this->error('❌ Falló el scraper de Teromatero');
                return $exit;
            }
            $this->newLine();
        } else {
            $this->warn('⏭  Teromatero omitido (--skip-tero)');
            $this->newLine();
        }

        // ====== SCOUT REINDEX ======
        if (! $noReindex) {
            $currentStep++;
            $this->info("▶  [{$currentStep}/{$totalSteps}] Reindexando Scout...");
            $this->newLine();

            $exit = $this->call('scout:flush', ['model' => 'App\\Models\\Product']);
            if ($exit !== 0) {
                $this->warn('⚠️  scout:flush salió con código ' . $exit);
            }

            $exit = $this->call('scout:import', ['model' => 'App\\Models\\Product']);
            if ($exit !== 0) {
                $this->error('❌ Falló la reindexación de Scout');
                return $exit;
            }
            $this->newLine();
        } else {
            $this->warn('⏭  Reindexado omitido (--no-reindex)');
            $this->newLine();
        }

        $elapsed = round(microtime(true) - $start, 2);

        $this->newLine();
        $this->info("✅ Sincronización completa en {$elapsed} s");
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Construye el array de opciones para pasar a los scrapers hijos.
     * Normaliza valores vacíos a null para no propagar strings vacíos.
     */
    private function buildOptions(): array
    {
        $opts = [];

        $pages = $this->option('pages');
        if ($pages !== null && $pages !== '' && (int) $pages > 0) {
            $opts['--pages'] = (int) $pages;
        }

        $delay = $this->option('delay');
        if ($delay !== null && $delay !== '' && (int) $delay >= 0) {
            $opts['--delay'] = (int) $delay;
        }

        if ($this->option('dry-run')) {
            $opts['--dry-run'] = true;
        }
        if ($this->option('fresh')) {
            $opts['--fresh'] = true;
        }
        $category = $this->option('category');
        if ($category !== null && $category !== '') {
            $opts['--category'] = $category;
        }
        if ($this->option('no-hide-missing')) {
            $opts['--no-hide-missing'] = true;
        }
        if ($this->option('detail')) {
            $opts['--detail'] = true;
        }
        $detailDelay = $this->option('detail-delay');
        if ($detailDelay !== null && $detailDelay !== '' && (float) $detailDelay >= 0) {
            $opts['--detail-delay'] = (float) $detailDelay;
        }

        return $opts;
    }
}
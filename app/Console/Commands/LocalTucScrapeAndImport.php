<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Comando para ejecutar en un solo paso:
 *   1. Scraper local con Playwright Chromium (node tuc-scraper/scraper.js)
 *   2. Importación directa del JSON generado a la base de datos (tuc:import)
 *
 * Uso:
 *   php artisan tuc:localimport              # Scraper completo + importación directa a DB
 *   php artisan tuc:localimport --test       # Prueba rápida: 5 productos + importación
 *   php artisan tuc:localimport --dry-run    # Scrapea y simula la importación sin guardar
 *   php artisan tuc:localimport --no-import  # Solo extrae el JSON sin tocar la base de datos
 */
class LocalTucScrapeAndImport extends Command
{
    protected $signature = 'tuc:localimport
        {--test      : Ejecutar en modo prueba: solo extrae 5 productos y los importa}
        {--dry-run   : Simular la importación en base de datos sin persistir cambios}
        {--no-import : Solo ejecutar el scraper y generar productos.json sin importar a la DB}';

    protected $description = 'Ejecuta el scraper local (Playwright Chromium) y luego importa automáticamente los productos a la base de datos';

    public function handle(): int
    {
        $this->newLine();
        $this->info('╔══════════════════════════════════════════╗');
        $this->info('║    TUSTECNOLOGÍATUC — TODO EN UNO        ║');
        $this->info('╚══════════════════════════════════════════╝');
        $this->newLine();

        $isTest = (bool) $this->option('test');
        $dryRun = (bool) $this->option('dry-run');
        $noImport = (bool) $this->option('no-import');

        $scriptPath = base_path('tuc-scraper/scraper.js');
        if (! file_exists($scriptPath)) {
            $this->error("❌ No se encontró el script del scraper en: {$scriptPath}");
            return self::FAILURE;
        }

        $command = ['node', 'scraper.js'];
        if ($isTest) {
            $command[] = '--test';
        }

        $this->info('🚀 [1/2] Iniciando Scraper local (Playwright Chromium)...');
        $this->line('   Directorio: ' . base_path('tuc-scraper'));
        $this->newLine();

        // Timeout generoso de 15 minutos para catálogos grandes
        $process = new Process($command, base_path('tuc-scraper'), null, null, 900);

        // Streaming en tiempo real de la salida del scraper a la consola de Artisan
        $exitCode = $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        if ($exitCode !== 0) {
            $this->newLine();
            $this->error('❌ El scraper falló. Se interrumpe la importación a la base de datos.');
            return self::FAILURE;
        }

        if ($noImport) {
            $this->newLine();
            $this->warn('⏭️  --no-import activo: Se generó el archivo JSON pero NO se importó a la DB.');
            return self::SUCCESS;
        }

        $jsonFile = $isTest
            ? 'tuc-scraper/productos_test.json'
            : 'tuc-scraper/productos.json';

        $this->newLine();
        $this->info('📥 [2/2] Importando productos a la base de datos local...');
        $this->line("   Archivo fuente: {$jsonFile}");

        return $this->call('tuc:import', [
            '--file'    => $jsonFile,
            '--dry-run' => $dryRun,
        ]);
    }
}

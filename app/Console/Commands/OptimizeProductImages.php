<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Descarga las imágenes de productos externos a local y las redimensiona.
 *
 * Hoy las imágenes se sirven directo desde Daz/Tuc (URLs externas).
 * Esto las baja a /storage/app/public/products/{id}.jpg para:
 *  - Reducir latencia
 *  - Que el sitio no se caiga si el proveedor se cae
 *  - Aplicar tamaño máximo (800x800)
 *
 * Uso:
 *   php artisan products:optimize-images
 *   php artisan products:optimize-images --limit=200
 *   php artisan products:optimize-images --force   # re-bajar incluso si ya existe local
 */
class OptimizeProductImages extends Command
{
    protected $signature = 'products:optimize-images
        {--limit=100 : Cantidad máxima de productos a procesar}
        {--force     : Re-bajar incluso si la imagen local ya existe}
        {--max-size=800 : Tamaño máximo (ancho/alto) en pixeles}';

    protected $description = 'Descarga imágenes externas de productos a local y las redimensiona';

    public function handle(): int
    {
        $limit  = max(1, (int) $this->option('limit'));
        $force  = (bool) $this->option('force');
        $maxSize = max(100, (int) $this->option('max-size'));

        Storage::disk('public')->makeDirectory('products');

        $query = Product::query()
            ->whereNotNull('image')
            ->where('image', 'like', 'http%')
            ->orderBy('id');

        $total = $query->count();
        $this->info("Procesando hasta {$limit} de {$total} productos con imagen externa");

        $bar = $this->output->createProgressBar(min($limit, $total));
        $bar->start();

        $stats = ['downloaded' => 0, 'skipped' => 0, 'failed' => 0];

        $query->limit($limit)->each(function (Product $product) use ($force, $maxSize, $bar, &$stats) {
            $bar->advance();

            $localPath = "products/{$product->id}.jpg";
            $disk = Storage::disk('public');

            if (! $force && $disk->exists($localPath)) {
                $stats['skipped']++;
                return;
            }

            try {
                $response = Http::timeout(15)->retry(2, 500)->get($product->image);
                if (! $response->successful()) {
                    throw new \RuntimeException("HTTP {$response->status()}");
                }

                $binary = $response->body();
                if (strlen($binary) < 1024) {
                    throw new \RuntimeException('Imagen demasiado pequeña (< 1KB)');
                }

                // Guardar siempre como JPG para uniformidad. Si tiene alpha (PNG), se pierde.
                // Para nuestro caso (imágenes de productos de retail) está perfecto.
                $img = $this->resize($binary, $maxSize);
                if ($img === null) {
                    // No se pudo procesar con GD, guardamos el original igual.
                    $disk->put($localPath, $binary);
                } else {
                    $disk->put($localPath, $img);
                }

                $product->image = $localPath;
                $product->saveQuietly(); // Evitar observers que invalidarían cache sin necesidad.
                $stats['downloaded']++;
            } catch (\Throwable $e) {
                $stats['failed']++;
                Log::warning('OptimizeProductImages: fallo', [
                    'product_id' => $product->id,
                    'image'      => $product->image,
                    'error'      => $e->getMessage(),
                ]);
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Concepto', 'Cantidad'],
            [
                ['Descargadas/redimensionadas', $stats['downloaded']],
                ['Omitidas (ya existían)',       $stats['skipped']],
                ['Fallaron',                     $stats['failed']],
            ]
        );

        // Invalidar cache público para que se vean las nuevas URLs.
        \App\Support\CacheHelper::flush(['products:public']);

        $this->info('✅ Listo. Imágenes disponibles en /storage/app/public/products/');
        $this->line('💡 Asegurate de tener el symlink: php artisan storage:link');

        return self::SUCCESS;
    }

    /**
     * Redimensiona una imagen binaria al tamaño máximo manteniendo proporción.
     * Devuelve null si GD no está disponible o la imagen no se puede decodificar.
     */
    private function resize(string $binary, int $maxSize): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        try {
            $src = @imagecreatefromstring($binary);
            if ($src === false) {
                return null;
            }

            $w = imagesx($src);
            $h = imagesy($src);

            if ($w <= $maxSize && $h <= $maxSize) {
                imagedestroy($src);
                return $binary; // Ya es suficientemente chica
            }

            if ($w > $h) {
                $newW = $maxSize;
                $newH = (int) round($h * ($maxSize / $w));
            } else {
                $newH = $maxSize;
                $newW = (int) round($w * ($maxSize / $h));
            }

            $dst = imagecreatetruecolor($newW, $newH);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

            ob_start();
            imagejpeg($dst, null, 85); // Calidad 85 = buen balance tamaño/calidad
            $data = ob_get_clean();

            imagedestroy($src);
            imagedestroy($dst);

            return $data;
        } catch (\Throwable $e) {
            return null;
        }
    }
}

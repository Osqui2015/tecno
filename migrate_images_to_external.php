<?php

/**
 * Script one-shot: para cada producto que aún tenga una imagen con path local
 * (/storage/products/xxx.png o products/xxx.png), visita su source_url en
 * dazimportadora, extrae la URL real de la imagen y actualiza el campo
 * products.image con esa URL.
 *
 * Después de correr este script podés borrar los archivos locales con:
 *   rm -rf storage/app/public/products/*
 *
 * Esto descarga cada producto 1 vez (más liviano que re-scrapear todo).
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

$baseUrl = 'https://dazimportadora.com.ar';

// Productos con imagen LOCAL (no URL externa) y con source_url para reextraer
$products = Product::whereNotNull('source_url')
    ->whereNotNull('external_id')
    ->where(function ($q) {
        $q->where('image', 'like', '/storage/%')
          ->orWhere('image', 'like', 'products/%')
          ->orWhere('image', 'like', 'http://localhost/%')
          ->orWhere('image', 'like', 'http://127.0.0.1/%');
    })
    ->get();

echo "🔄 Productos con imagen local a migrar: " . $products->count() . PHP_EOL;
echo str_repeat('─', 60) . PHP_EOL;

if ($products->isEmpty()) {
    echo "✅ Nada para migrar. Todos los productos ya tienen URL externa." . PHP_EOL;
    exit(0);
}

$ok = 0;
$skip = 0;
$fail = 0;
$headers = [
    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
    'Accept-Language' => 'es-AR,es;q=0.9,en;q=0.8',
];

foreach ($products as $i => $product) {
    echo "[" . ($i + 1) . "/" . $products->count() . "] " . $product->external_id . " ... ";

    try {
        $response = Http::withHeaders($headers)
            ->timeout(20)
            ->get($product->source_url);

        if (! $response->successful()) {
            echo "❌ HTTP {$response->status()}\n";
            $fail++;
            continue;
        }

        $html = $response->body();

        // Estrategia 1: og:image meta (la más confiable para WooCommerce)
        $externalUrl = null;
        if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            $externalUrl = $m[1];
        }

        // Estrategia 2: featured image JSON-LD
        if (! $externalUrl && preg_match('/"image"\s*:\s*\[?\s*"([^"]+\.(?:jpg|jpeg|png|webp|gif))"/i', $html, $m)) {
            $externalUrl = $m[1];
        }

        // Estrategia 3: gallery_image (WooCommerce suele tener un data attribute con la imagen principal)
        if (! $externalUrl && preg_match('/class=["\'][^"\']*wp-post-image[^"\']*["\'][^>]+src=["\']([^"\']+\.(?:jpg|jpeg|png|webp|gif))/i', $html, $m)) {
            $externalUrl = $m[1];
        }

        // Estrategia 4: primera imagen dentro del contenedor woocommerce-product-gallery
        if (! $externalUrl && preg_match_all('/<img[^>]+src=["\']([^"\']+\.(?:jpg|jpeg|png|webp|gif))["\']/i', $html, $matches)) {
            foreach ($matches[1] as $candidate) {
                // Priorizar imágenes de wp-content/uploads
                if (str_contains($candidate, 'wp-content/uploads')) {
                    $externalUrl = $candidate;
                    break;
                }
            }
        }

        if (! $externalUrl) {
            echo "❌ No se encontró imagen en la página\n";
            $fail++;
            continue;
        }

        // Normalizar a URL absoluta
        if (str_starts_with($externalUrl, '//')) {
            $externalUrl = 'https:' . $externalUrl;
        } elseif (str_starts_with($externalUrl, '/')) {
            $externalUrl = $baseUrl . $externalUrl;
        }

        // Limpiar query string basura (?x=12345)
        $externalUrl = preg_replace('/\?.*$/i', '', $externalUrl);
        // Limpiar sufijos de tamaño de WooCommerce (-800x800.png → .png) — opcional, los dejamos

        $product->image = $externalUrl;
        $product->save();

        echo "✅ " . substr($externalUrl, 0, 70) . (strlen($externalUrl) > 70 ? '…' : '') . "\n";
        $ok++;

        usleep(200000); // 0.2s entre requests
    } catch (\Throwable $e) {
        echo "❌ " . $e->getMessage() . "\n";
        $fail++;
        Log::warning('migrate_images_to_external: error', [
            'external_id' => $product->external_id,
            'source_url' => $product->source_url,
            'error' => $e->getMessage(),
        ]);
    }
}

echo str_repeat('─', 60) . PHP_EOL;
echo "✅ Migradas a URL externa: {$ok}\n";
echo "❌ Fallidas: {$fail}\n";
echo PHP_EOL;
echo "💡 Tip: ahora podés borrar las imágenes locales con:" . PHP_EOL;
echo "   rm -rf storage/app/public/products/*" . PHP_EOL;

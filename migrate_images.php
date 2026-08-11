<?php

/**
 * Script one-shot: descarga las imágenes de dazimportadora para los
 * productos existentes y actualiza el path en products.image a /storage/products/xxx
 *
 * Esto soluciona el problema de hotlink protection (Dazimportadora bloquea
 * Referers externos con 403, así que servimos las imágenes desde nuestro server).
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

$products = Product::whereNotNull('image')
    ->where('image', 'like', 'https://%')
    ->get();

echo "📦 Productos con imagen externa a migrar: " . $products->count() . PHP_EOL;
echo str_repeat('─', 60) . PHP_EOL;

$ok = 0;
$fail = 0;
$skip = 0;

foreach ($products as $i => $product) {
    $url = $product->image;
    $externalId = $product->external_id ?? 'unknown_' . $product->id;

    // Sacar extensión
    $pathInfo = parse_url($url, PHP_URL_PATH);
    $ext = strtolower(pathinfo($pathInfo ?? '', PATHINFO_EXTENSION) ?: 'png');
    if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) $ext = 'png';
    if ($ext === 'jpeg') $ext = 'jpg';

    $filename = "products/{$externalId}.{$ext}";
    $publicUrl = '/storage/' . $filename;

    // Si ya existe localmente, solo actualizar el campo
    if (Storage::disk('public')->exists($filename)) {
        $product->image = $publicUrl;
        $product->save();
        $skip++;
        echo "[" . ($i + 1) . "/" . $products->count() . "] ⏭️  Ya existe: {$externalId}\n";
        continue;
    }

    echo "[" . ($i + 1) . "/" . $products->count() . "] ⬇️  {$externalId} ... ";

    try {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Referer' => 'https://dazimportadora.com.ar/productos/',
            'Accept' => 'image/png,image/jpeg,image/webp,image/*',
        ])->timeout(20)->get($url);

        if (! $response->successful()) {
            echo "❌ HTTP {$response->status()}\n";
            $fail++;
            continue;
        }

        $body = $response->body();

        $isImage =
            substr($body, 0, 4) === "\x89PNG" ||
            substr($body, 0, 3) === "\xFF\xD8\xFF" ||
            substr($body, 0, 6) === "GIF87a" ||
            substr($body, 0, 6) === "GIF89a" ||
            (substr($body, 0, 4) === "RIFF" && substr($body, 8, 4) === "WEBP");

        if (! $isImage) {
            echo "❌ No es imagen\n";
            $fail++;
            continue;
        }

        Storage::disk('public')->put($filename, $body);
        $product->image = $publicUrl;
        $product->save();

        echo "✅ " . round(strlen($body) / 1024) . " KB\n";
        $ok++;
    } catch (\Throwable $e) {
        echo "❌ " . $e->getMessage() . "\n";
        $fail++;
    }

    usleep(150000); // 0.15s entre requests para no saturar
}

echo str_repeat('─', 60) . PHP_EOL;
echo "✅ Migradas: {$ok}\n";
echo "⏭️  Saltadas (ya existían): {$skip}\n";
echo "❌ Fallidas: {$fail}\n";
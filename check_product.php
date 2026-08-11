<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;

$term = 'CARGADOR CABEZAL HYTOSHY';

echo "=== Búsqueda LIKE en BD ===\n";
$rows = Product::where('name', 'like', "%{$term}%")
    ->orWhere('description', 'like', "%{$term}%")
    ->orWhere('sku', 'like', "%{$term}%")
    ->get();

if ($rows->isEmpty()) {
    echo "No se encontraron coincidencias exactas. Probemos con términos sueltos:\n\n";
    foreach (['CARGADOR', 'CABEZAL', 'HYTOSHY'] as $t) {
        $r = Product::where('name', 'like', "%{$t}%")->get();
        echo "  '{$t}' → {$r->count()} resultados\n";
    }
    exit;
}

foreach ($rows as $p) {
    echo str_repeat('-', 60) . "\n";
    echo "ID:           {$p->id}\n";
    echo "Nombre:       {$p->name}\n";
    echo "SKU:          " . ($p->sku ?? '(vacío)') . "\n";
    echo "Stock:        {$p->stock}\n";
    echo "Active:       " . ($p->active ? 'true' : 'false') . "\n";
    echo "External ID:  " . ($p->external_id ?? '(NULL — producto MANUAL)') . "\n";
    echo "Categoría ID: " . ($p->category_id ?? '(NULL)') . "\n";
    echo "¿Aparece en /api/products?: ";
    $passes = $p->active && !is_null($p->external_id);
    echo ($passes ? "✅ SÍ" : "❌ NO") . "\n";
}

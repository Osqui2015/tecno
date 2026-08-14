<?php
// Script de test rápido: crear un producto, modificarlo, ver el historial.

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use App\Models\Category;

$cat = Category::first();
if (! $cat) {
    echo "ERROR: no hay categorías. Creá una primero.\n";
    exit(1);
}

// Simular un admin "logueado" en consola para que el source sea 'admin'.
$admin = App\Models\User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->first();
if ($admin) {
    auth()->login($admin);
}

echo "=== Test 1: Crear producto ===\n";
$p = Product::create([
    'name'        => 'TEST historial create',
    'price'       => 100,
    'stock'       => 10,
    'category_id' => $cat->id,
    'active'      => true,
]);
echo "  Producto creado: id={$p->id}, last_updated_at={$p->last_updated_at}\n";

echo "\n=== Test 2: Modificar precio ===\n";
$p->update(['price' => 150, 'cash_price' => 130]);
echo "  Producto actualizado: price={$p->fresh()->price}, cash_price={$p->fresh()->cash_price}\n";

echo "\n=== Test 3: Simular cambio desde scraper (daz) ===\n";
app()->instance('scrape_in_progress', true);
app()->instance('scrape_origin', 'daz');
$p->update(['price' => 145, 'stock' => 8]);
app()->forgetInstance('scrape_in_progress');
app()->forgetInstance('scrape_origin');
echo "  Producto modificado desde scraper\n";

echo "\n=== Historial del producto ===\n";
$rows = $p->updateHistory()->get();
echo "  Total: " . $rows->count() . " filas\n";
foreach ($rows as $r) {
    $actor = $r->actor?->name ?? '—';
    $fields = implode(', ', $r->changed_fields ?? []);
    echo sprintf(
        "  - [%s] %s | %s | %s | por=%s | campos=[%s]\n",
        $r->created_at->format('H:i:s'),
        $r->event,
        $r->source,
        $r->source_label,
        $actor,
        $fields
    );
}

echo "\n=== Snapshot del scraper ===\n";
$snap = App\Support\ScrapeSchedule::snapshot();
echo "  Próxima corrida: {$snap['next_run_human']} (faltan {$snap['seconds_until_next']}s)\n";
echo "  Última corrida real: " . ($snap['last_actual_run_human'] ?? '—') . "\n";
echo "  Intervalo: {$snap['interval_hours']}h\n";

// Limpiar el producto de test
echo "\n=== Limpieza ===\n";
$p->delete();
echo "  Producto TEST eliminado.\n";
echo "\n✅ Todos los tests pasaron.\n";

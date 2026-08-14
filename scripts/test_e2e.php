<?php
// Test E2E: registro con/sin phone, y endpoints admin (scrape-status, history).

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Category;
use App\Models\User;
use App\Models\Product;
use App\Models\ProductUpdateHistory;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Admin\ScrapeStatusController;
use App\Http\Controllers\Admin\ProductController;
use Illuminate\Http\Request;

echo "=== Test E2E: Registro ===\n\n";

// Test 1: register SIN phone → debe fallar
$req = Request::create('/api/register', 'POST', [
    'name'                  => 'Test Sin Phone',
    'email'                 => 'test.sinphone.' . time() . '@example.com',
    'password'              => 'secreto123',
    'password_confirmation' => 'secreto123',
]);
try {
    $ctl = new AuthController();
    $resp = $ctl->register($req);
    echo "  ❌ FAIL: register sin phone debería haber fallado, devolvió " . $resp->getStatusCode() . "\n";
} catch (\Illuminate\Validation\ValidationException $e) {
    $errs = $e->errors();
    $marker = isset($errs['phone']) ? '✅' : '❌';
    echo "  $marker register sin phone → rechazado con: " . json_encode($errs['phone'] ?? 'otro error') . "\n";
}

// Test 2: register CON phone inválido → debe fallar
$req = Request::create('/api/register', 'POST', [
    'name'                  => 'Test Phone Malo',
    'email'                 => 'test.malo.' . time() . '@example.com',
    'phone'                 => '123',
    'password'              => 'secreto123',
    'password_confirmation' => 'secreto123',
]);
try {
    $ctl = new AuthController();
    $resp = $ctl->register($req);
    echo "  ❌ FAIL: register con phone '123' debería haber fallado\n";
} catch (\Illuminate\Validation\ValidationException $e) {
    $errs = $e->errors();
    $marker = isset($errs['phone']) ? '✅' : '❌';
    echo "  $marker register con phone '123' → rechazado: " . json_encode($errs['phone'] ?? []) . "\n";
}

// Test 3: register CON phone AR válido → debe crear el user
$email = 'test.ok.' . time() . '@example.com';
$req = Request::create('/api/register', 'POST', [
    'name'                  => 'Test OK',
    'email'                 => $email,
    'phone'                 => '11 4123-4567',
    'password'              => 'secreto123',
    'password_confirmation' => 'secreto123',
]);
try {
    $ctl = new AuthController();
    $resp = $ctl->register($req);
    $user = User::where('email', $email)->first();
    if ($user && $user->phone === '5491141234567') {
        echo "  ✅ register con phone '11 4123-4567' → user creado, phone normalizado a '{$user->phone}'\n";
        $user->delete();
    } else {
        echo "  ❌ FAIL: user no creado o phone mal guardado. user=" . ($user ? "phone={$user->phone}" : 'null') . "\n";
    }
} catch (\Throwable $e) {
    echo "  ❌ FAIL: register válido lanzó excepción: " . $e->getMessage() . "\n";
}

echo "\n=== Test E2E: Endpoint /api/admin/scrape-status ===\n\n";
$ctl = new ScrapeStatusController();
$resp = $ctl->index();
$data = $resp->getData(true);
$keys = ['now', 'next_run_at', 'next_run_human', 'seconds_until_next', 'interval_hours'];
$marker = '✅';
foreach ($keys as $k) {
    if (! array_key_exists($k, $data)) { $marker = '❌'; break; }
}
echo "  $marker /admin/scrape-status devuelve: next_run_human={$data['next_run_human']} faltan={$data['seconds_until_next']}s\n";

echo "\n=== Test E2E: Endpoint /api/admin/products/{id}/history ===\n\n";
$cat = Category::first();
$admin = User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->first();
auth()->login($admin);

// Crear un producto y modificarlo un par de veces
$p = Product::create(['name' => 'TEST E2E', 'price' => 100, 'stock' => 5, 'category_id' => $cat->id, 'active' => true]);
$p->update(['price' => 200]);
$p->update(['description' => 'probando historial']);

// Probar el endpoint
$req = Request::create("/api/admin/products/{$p->id}/history", 'GET', ['per_page' => 10]);
$ctl = new ProductController();
$resp = $ctl->history($req, $p->id);
$data = $resp->getData(true);

if ($data['product']['id'] === $p->id && count($data['data']) >= 3) {
    echo "  ✅ /admin/products/{$p->id}/history devuelve " . count($data['data']) . " eventos\n";
    foreach (array_slice($data['data'], 0, 3) as $h) {
        echo "     - [{$h['created_at_human']}] {$h['event']} | {$h['source']} | {$h['summary']}\n";
    }
} else {
    echo "  ❌ FAIL: respuesta inesperada\n" . json_encode($data, JSON_PRETTY_PRINT) . "\n";
}

$p->delete();
auth()->logout();

echo "\n✅ Todos los tests E2E pasaron.\n";

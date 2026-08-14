<?php
// Test de la validación de phone AR en AuthController.

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\Api\AuthController;

$ctl = new AuthController();

echo "=== Test validación de phone AR ===\n\n";

$cases = [
    // válidos
    ['1141234567',     true,  'local 10 dígitos'],
    ['11 4123-4567',   true,  'con separadores'],
    ['+54 9 11 4123-4567', true, 'internacional con +54 9'],
    ['+541141234567',  true,  'internacional sin separadores'],
    ['5491141234567',  true,  'prefijo 549'],
    ['3514123456',     true,  'Córdoba sin 15'],

    // inválidos
    ['123',            false, 'muy corto'],
    ['123456789012345678', false, 'muy largo (>30)'],
    ['',               false, 'vacío'],
    ['abc',            false, 'no numérico'],
    ['11abc1234',      false, 'con letras'],
    ['15 4123-4567',   false, '15 sin código de área (ambiguo)'],
    ['1541234567',     false, '15 sin código de área (sin espacios)'],
];

$rule = AuthController::phoneArRule();

foreach ($cases as [$input, $expected, $label]) {
    $valid = preg_match($rule, $input) === 1;
    $marker = $valid === $expected ? '✅' : '❌';
    echo "  $marker [{$label}] '{$input}' → " . ($valid ? 'VÁLIDO' : 'INVÁLIDO') . "\n";
}

echo "\n=== Test normalización de phone AR ===\n\n";

$normCases = [
    '1141234567'         => '5491141234567',
    '11 4123-4567'       => '5491141234567',
    '+54 9 11 4123-4567' => '5491141234567',
    '5491141234567'      => '5491141234567',
    '15 4123-4567'       => '5491141234567', // con 15 prefijo (formato viejo)
    '3514123456'         => '5493514123456', // córdoba
];

foreach ($normCases as $input => $expected) {
    $out = AuthController::normalizePhoneAr($input);
    $marker = $out === $expected ? '✅' : '❌';
    echo "  $marker '{$input}' → '{$out}' (esperado '{$expected}')\n";
}

echo "\n✅ Tests de phone completados.\n";

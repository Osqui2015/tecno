<?php
$url = 'https://dazimportadora.com.ar/wp-content/uploads/2026/07/ARTICULOS-JULIO-26-36-3-800x800.png';

echo "=== Test de hotlink protection ===\n\n";

// Test 1: Sin Referer
echo "1) Sin Referer header:\n";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_NOBODY, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0');
$body = curl_exec($ch);
$info = curl_getinfo($ch);
curl_close($ch);
echo "   HTTP: {$info['http_code']}\n";
echo "   Size: " . strlen($body) . " bytes\n";
echo "   Type: " . ($info['content_type'] ?? 'unknown') . "\n";
echo "   First 30 chars: " . substr(bin2hex(substr($body, 0, 30)), 0, 60) . "\n";
echo "   Is PNG? " . (substr($body, 0, 4) === "\x89PNG" ? 'YES ✅' : 'NO ❌') . "\n\n";

// Test 2: Con Referer = localhost (lo que enviará tu navegador)
echo "2) Con Referer = http://127.0.0.1:8000 (lo que manda tu navegador):\n";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0');
curl_setopt($ch, CURLOPT_REFERER, 'http://127.0.0.1:8000/productos');
$body = curl_exec($ch);
$info = curl_getinfo($ch);
curl_close($ch);
echo "   HTTP: {$info['http_code']}\n";
echo "   Size: " . strlen($body) . " bytes\n";
echo "   Type: " . ($info['content_type'] ?? 'unknown') . "\n";
echo "   Is HTML? " . (strpos($body, '<html') !== false ? 'YES ❌ (HOTLINK BLOCKED!)' : 'NO') . "\n";
echo "   Is PNG? " . (substr($body, 0, 4) === "\x89PNG" ? 'YES ✅' : 'NO ❌') . "\n\n";

// Test 3: Con Referer = dazimportadora (lo que mandaría el navegador si cargaras la imagen directamente desde su sitio)
echo "3) Con Referer = https://dazimportadora.com.ar (caso válido para ellos):\n";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0');
curl_setopt($ch, CURLOPT_REFERER, 'https://dazimportadora.com.ar/productos/');
$body = curl_exec($ch);
$info = curl_getinfo($ch);
curl_close($ch);
echo "   HTTP: {$info['http_code']}\n";
echo "   Size: " . strlen($body) . " bytes\n";
echo "   Type: " . ($info['content_type'] ?? 'unknown') . "\n";
echo "   Is PNG? " . (substr($body, 0, 4) === "\x89PNG" ? 'YES ✅' : 'NO ❌') . "\n";
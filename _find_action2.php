<?php
$url = 'https://www.tustecnologiastuc.com/tienda/';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
]);
$body = curl_exec($ch);
curl_close($ch);

// Guardar el body para inspección
file_put_contents('dump_tienda.html', $body);
echo "Body guardado en dump_tienda.html (" . strlen($body) . " bytes)\n";

// Buscar la URL ajax de woodmart
if (preg_match('/woodmart_ajax_url["\']?\s*[:=]\s*["\']([^"\']+)/i', $body, $m)) {
    echo "woodmart_ajax_url: " . $m[1] . "\n";
}

// Buscar el nombre de la acción
if (preg_match('/action["\']?\s*[:=]\s*["\'](woodmart[a-z_]+)["\']/i', $body, $m)) {
    echo "action: " . $m[1] . "\n";
}

// Buscar el nonce
if (preg_match('/nonce["\']?\s*[:=]\s*["\']([a-f0-9]+)["\']/i', $body, $m)) {
    echo "nonce: " . $m[1] . "\n";
}

// Buscar en JSON
if (preg_match('/"action"\s*:\s*"([^"]+)"/i', $body, $m)) {
    echo "action JSON: " . $m[1] . "\n";
}

// Buscar el get_products endpoint
if (preg_match('/wp_ajax_url.*?["\']([^"\']*admin-ajax[^"\']*)["\']/', $body, $m)) {
    echo "wp_ajax_url: " . $m[1] . "\n";
}

// Buscar función woodmart que llama a admin-ajax
if (preg_match_all('/woodmart[a-zA-Z_]+\s*[:=]\s*function[^;}]+/i', $body, $matches)) {
    echo "Funciones woodmart encontradas:\n";
    foreach ($matches[0] as $i => $match) {
        if ($i > 5) break;
        echo "  " . substr($match, 0, 200) . "\n";
    }
}

// Buscar cualquier AJAX action en el script
if (preg_match_all('/["\']([a-z_]+_ajax_[a-z_]+)["\']/i', $body, $matches)) {
    echo "AJAX actions encontradas:\n";
    foreach (array_unique($matches[1]) as $action) {
        echo "  $action\n";
    }
}

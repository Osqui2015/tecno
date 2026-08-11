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

if (preg_match('/data-attributes-url="([^"]+)"/i', $body, $m)) {
    echo "data-attributes-url: " . html_entity_decode($m[1]) . "\n";
}

if (preg_match('/action=([^&"]+)/i', $body, $m)) {
    echo "action: " . $m[1] . "\n";
}

// Buscar las funciones JS que llaman a admin-ajax
if (preg_match('/admin_ajax[_\.]?\s*=\s*["\']([^"\']+)["\']/i', $body, $m)) {
    echo "admin_ajax var: " . $m[1] . "\n";
}

// Buscar el nombre de la función JS de Woodmart
if (preg_match('/"action"\s*:\s*["\']([^"\']+)["\']/i', $body, $m)) {
    echo "JSON action: " . $m[1] . "\n";
}

// Buscar event handlers y onclick
if (preg_match('/(woodmart|wd_)[a-z_]*load[a-z_]*more/ix', $body, $m)) {
    echo "Woodmart load function: " . $m[0] . "\n";
}

// El action específico debería estar en un atributo o en el nonce
if (preg_match('/data-[a-z_-]+action=["\']([^"\']+)["\']/i', $body, $m)) {
    echo "data action attribute: " . $m[1] . "\n";
}

// Vamos a buscar en los scripts inline
if (preg_match_all('/admin-ajax\.php[^"\'<>\s]*["\']?[^"\'<>\s]*/i', $body, $matches)) {
    echo "admin-ajax references:\n";
    foreach (array_unique($matches[0]) as $ref) {
        echo "  $ref\n";
    }
}

// Datos del último "load more" button
if (preg_match('/<a[^>]*class="[^"]*load-more[^"]*"[^>]*>/i', $body, $m)) {
    echo "Load more button: " . substr($m[0], 0, 300) . "\n";
}

if (preg_match('/<button[^>]*class="[^"]*load-more[^"]*"[^>]*>/i', $body, $m)) {
    echo "Load more button: " . substr($m[0], 0, 300) . "\n";
}

// Otro patrón: buscar woodmart_ajax
if (preg_match_all('/woodmart[a-z_-]*ajax/ix', $body, $matches)) {
    echo "Woodmart AJAX refs:\n";
    foreach (array_unique($matches[0]) as $ref) {
        echo "  $ref\n";
    }
}

<?php
// Leer el archivo y buscar patrones línea por línea
$file = 'dump_tienda.html';
$content = file_get_contents($file);

// Eliminar saltos de línea entre paréntesis (CSS/JS minificado)
$content = preg_replace('/\s+/', ' ', $content);

// Buscar el nombre de la acción AJAX
$patterns = [
    '/["\']action["\']\s*[:=]\s*["\']([a-z_0-9]+)["\']/i',
    '/["\']action["\']\s*:\s*["\']([a-z_0-9]+)["\']/i',
    '/do_action\(\s*["\']([a-z_0-9_]+)["\']/i',
    '/wp_ajax_[a-z_0-9_]+/i',
    '/woodmart_[a-z_0-9_]+/i',
    '/wd_ajax_[a-z_0-9_]+/i',
];

foreach ($patterns as $pat) {
    if (preg_match_all($pat, $content, $matches)) {
        $unique = array_unique($matches[0]);
        echo "Pattern: $pat\n";
        foreach (array_slice($unique, 0, 20) as $m) {
            echo "  $m\n";
        }
        echo "\n";
    }
}

// También buscar el atributo data-page o data-ajax
if (preg_match_all('/(data-page|data-ajax|data-loop|data-attributes-url)="([^"]+)"/i', $content, $matches)) {
    echo "Data attributes:\n";
    foreach (array_unique($matches[0]) as $i => $m) {
        echo "  $m\n";
        if ($i > 10) break;
    }
}

// Buscar el wp_localize_script
if (preg_match_all('/var\s+(\w+)\s*=\s*\{[^}]*action[\'"]?\s*:\s*["\']([a-z_0-9]+)["\']/i', $content, $matches)) {
    echo "Localized vars:\n";
    foreach ($matches[0] as $m) {
        echo "  $m\n";
    }
}

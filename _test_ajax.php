<?php
// Probar el endpoint AJAX exacto que usó el usuario en el navegador
$url = 'https://www.tustecnologiastuc.com/wp-admin/admin-ajax.php';

$params = [
    'loop' => 35,
    'woo_ajax' => 1,
    'atts[product_hover_type]' => 'predefined',
    'atts[product_custom_hover]' => '122488',
    'atts[img_size]' => 'large',
    'atts[img_size_custom]' => '0',
    'atts[products_view]' => 'grid',
    'atts[products_columns]' => '5',
    'atts[products_columns_tablet]' => '4',
    'atts[products_columns_mobile]' => '2',
    'atts[products_spacing]' => '20',
    'atts[products_spacing_tablet]' => '',
    'atts[products_spacing_mobile]' => '',
    'atts[products_list_spacing]' => '30',
    'atts[products_list_spacing_tablet]' => '',
    'atts[products_list_spacing_mobile]' => '',
    'atts[product_hover]' => 'fw-button',
    'atts[products_bordered_grid]' => '0',
    'atts[products_bordered_grid_style]' => 'outside',
    'atts[products_color_scheme]' => 'default',
    'atts[products_with_background]' => '1',
    'atts[products_shadow]' => '0',
];

$ch = curl_init($url . '?' . http_build_query($params));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    CURLOPT_HTTPHEADER => [
        'X-Requested-With: XMLHttpRequest',
        'Accept: application/json, text/html, */*',
        'Referer: https://www.tustecnologiastuc.com/tienda/',
    ],
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP code: $code\n";
echo "Body length: " . strlen($body) . "\n";

$data = json_decode($body, true);
if (is_array($data)) {
    echo "JSON válido, keys: " . implode(', ', array_keys($data)) . "\n";
    echo "Items length: " . strlen($data['items'] ?? '') . "\n";
    echo "NextPage: " . ($data['nextPage'] ?? 'null') . "\n";
    echo "resultCount: " . ($data['resultCount'] ?? 'null') . "\n";
    echo "status: " . ($data['status'] ?? 'null') . "\n";

    // Contar productos en el HTML
    $count = substr_count($data['items'] ?? '', 'class="wd-product wd-col');
    echo "Productos en items: $count\n";

    // Mostrar el primer producto como ejemplo
    if (preg_match('/data-id="(\d+)"[^>]*class="[^"]*wd-product[^"]*product[^"]*"/', $data['items'], $m)) {
        echo "Primer producto data-id: " . $m[1] . "\n";
    }
} else {
    echo "Body: " . substr($body, 0, 500) . "\n";
}

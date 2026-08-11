<?php
$urls = [
    'https://www.tustecnologiastuc.com/',
    'https://www.tustecnologiastuc.com/shop/',
    'https://www.tustecnologiastuc.com/tienda/',
    'https://www.tustecnologiastuc.com/productos/',
    'https://www.tustecnologiastuc.com/?product_cat=',
    'https://www.tustecnologiastuc.com/?post_type=product',
];

foreach ($urls as $url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $url_effective = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    echo str_pad($url, 60) . " → HTTP $code\n";
    if ($code === 200) {
        echo "  effective: $url_effective\n";
        // Buscar la URL de shop / categoría / loop
        if (preg_match('/class="[^"]*woocommerce-loop[^"]*"/i', $body)) {
            echo "  ✓ Tiene woocommerce-loop\n";
        }
        if (preg_match('/data-attributes-url="([^"]+)"/i', $body, $m)) {
            echo "  ✓ data-attributes-url: " . $m[1] . "\n";
        }
        if (preg_match('/class="[^"]*wd-products[^"]*"/i', $body)) {
            echo "  ✓ Contiene wd-products (Woodmart)\n";
        }
        // Buscar el atributo data-paged o pagination
        if (preg_match('/data-paged="([^"]+)"/i', $body, $m)) {
            echo "  ✓ data-paged: " . $m[1] . "\n";
        }
        // Guardar el body para inspeccionar
        if (isset($_GET['dump']) && $_GET['dump'] === $url) {
            file_put_contents('dump.html', $body);
            echo "  → dump.html guardado\n";
        }
    }
}

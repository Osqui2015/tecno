<?php
$url = 'https://www.tustecnologiastuc.com/wp-content/themes/woodmart/js/scripts/wc/productsLoadMore.min.js';
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_USERAGENT => 'Mozilla/5.0',
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

file_put_contents('wd_pmlm.js', $body);
echo "HTTP: $code, size: " . strlen($body) . "\n";
echo "Body: " . substr($body, 0, 3000) . "\n";

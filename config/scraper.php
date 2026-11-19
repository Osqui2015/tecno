<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CA Bundle para los scrapers
    |--------------------------------------------------------------------------
    |
    | Ruta a un bundle de Autoridades Certificadoras (cacert.pem) que se
    | usa para verificar los certificados TLS de los sitios que se scrapean.
    |
    | Si se deja en null, la app resuelve el bundle automáticamente:
    |  1. `curl.cainfo` / `openssl.cafile` del php.ini
    |  2. Rutas habituales de PHP y Laragon (extras/ssl/cacert.pem)
    |  3. Descargándolo de https://curl.se/ca/cacert.pem al storage
    |
    | Sin un CA bundle, las peticiones HTTPS fallan con "cURL error 60"
    | y los scrapers terminan vaciando el catálogo por error.
    |
    */

    'ca_bundle' => env('SCRAPER_CA_BUNDLE'),

];

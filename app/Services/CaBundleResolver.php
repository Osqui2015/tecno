<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Resuelve la configuración de TLS (CA bundle) para las peticiones HTTP
 * salientes de los scrapers.
 *
 * POR QUÉ EXISTE:
 *
 * En entornos Windows (Laragon) la build de PHP suele venir sin un bundle
 * de CA configurado: `curl.cainfo` y `openssl.cafile` vacíos y sin el
 * archivo `extras/ssl/cacert.pem`. Con esa configuración, TODA petición
 * HTTPS falla con:
 *
 *     cURL error 60: SSL certificate OpenSSL verify result:
 *     unable to get local issuer certificate (20)
 *
 * Los scrapers veían eso como "HTTP 403" porque el servidor responde 403
 * cuando no puede validar la sesión... pero en realidad la petición ni
 * siquiera llegaba a completarse y el catálogo completo terminaba marcado
 * como faltante (active=0, stock=0), leaving el store sin productos.
 *
 * Este helper busca un CA bundle válido en:
 *  1. La variable de entorno / config `SCRAPER_CA_BUNDLE` (si está definida).
 *  2. `curl.cainfo` / `openssl.cafile` del php.ini (si tienen valor).
 *  3. Las rutas habituales de PHP y Laragon.
 *  4. Descargándolo de https://curl.se/ca/cacert.pem (una sola vez) al
 *     directorio de storage, si nada de lo anterior funciona.
 *
 * Si no se puede obtener un bundle, se devuelve `verify => false` como
 * último recurso para que el scraping funcione igual, aunque conviene
 * saber que la verificación TLS queda desactivada.
 */
class CaBundleResolver
{
    /**
     * Cacheado en memoria durante el proceso (evita repetir el búsqueda).
     */
    private static ?string $resolved = null;

    private static bool $resolvedChecked = false;

    /**
     * ¿Se pudo verificar el certificado con un CA bundle real?
     */
    private static bool $verified = false;

    /**
     * Devuelve la ruta a un CA bundle utilizable, o null si no hay ninguno.
     */
    public static function caBundlePath(): ?string
    {
        if (self::$resolvedChecked) {
            return self::$resolved;
        }

        self::$resolvedChecked = true;

        // 1) Configuración explícita de la app.
        $configured = config('scraper.ca_bundle')
            ?: env('SCRAPER_CA_BUNDLE')
            ?: env('CURL_CAINFO');

        if ($configured && is_file($configured)) {
            self::$resolved = $configured;
            self::$verified = true;

            return self::$resolved;
        }

        // 2) Configuración de PHP (php.ini).
        foreach (['curl.cainfo', 'openssl.cafile'] as $key) {
            $value = (string) ini_get($key);
            if ($value !== '' && is_file($value)) {
                self::$resolved = $value;
                self::$verified = true;

                return self::$resolved;
            }
        }

        // 3) Rutas habituales de PHP / Laragon.
        foreach (self::candidatePaths() as $path) {
            if (is_file($path)) {
                self::$resolved = $path;
                self::$verified = true;

                return self::$resolved;
            }
        }

        // 4) Descargar el bundle oficial de curl.
        $downloaded = self::downloadBundle();
        if ($downloaded !== null) {
            self::$resolved = $downloaded;
            self::$verified = true;

            return self::$resolved;
        }

        self::$resolved = null;
        self::$verified = false;

        return null;
    }

    /**
     * Opciones de cURL para verificar TLS usando el CA bundle resuelto.
     *
     * @return array<string, mixed>
     */
    public static function curlOptions(): array
    {
        $path = self::caBundlePath();

        if ($path !== null) {
            return ['verify' => $path];
        }

        // Sin CA bundle: desactivamos la verificación (último recurso).
        Log::warning(
            'Scraper: no se encontró un CA bundle; se desactiva la verificación TLS. '
            . 'Descargá https://curl.se/ca/cacert.pem y definí SCRAPER_CA_BUNDLE en .env.'
        );

        return ['verify' => false];
    }

    /**
     * ¿La verificación TLS está activa con un CA bundle real?
     */
    public static function isVerified(): bool
    {
        self::caBundlePath();

        return self::$verified;
    }

    /**
     * Rutas donde suele estar el cacert.pem en Windows/Linux.
     *
     * @return array<int, string>
     */
    private static function candidatePaths(): array
    {
        $phpBinary = PHP_BINARY;              // I:\laragon\bin\php\php-8.3.36-...\php.exe
        $phpDir   = dirname($phpBinary);

        $paths = [
            $phpDir . '\extras\ssl\cacert.pem',
            $phpDir . '/extras/ssl/cacert.pem',
            $phpDir . '\extras\openssl\cacert.pem',
            $phpDir . '/extras/openssl/cacert.pem',
            $phpDir . '\lib\curl\cacert.pem',
            $phpDir . '/lib/curl/cacert.pem',
            storage_path('app/certificates/cacert.pem'),
            base_path('storage/app/certificates/cacert.pem'),
        ];

        // Laragon: I:\laragon\bin\php\php-8.3.36-... -> I:\laragon\bin\php
        $laragonPhpRoot = dirname($phpDir);
        if (is_dir($laragonPhpRoot)) {
            foreach (glob($laragonPhpRoot . DIRECTORY_SEPARATOR . 'php-*') ?: [] as $versionDir) {
                $paths[] = $versionDir . '\extras\ssl\cacert.pem';
                $paths[] = $versionDir . '/extras/ssl/cacert.pem';
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * Descarga el CA bundle oficial de curl al storage de la app.
     */
    private static function downloadBundle(): ?string
    {
        $targetDir = storage_path('app/certificates');
        $target    = $targetDir . DIRECTORY_SEPARATOR . 'cacert.pem';

        if (! is_dir($targetDir) && ! @mkdir($targetDir, 0775, true) && ! is_dir($targetDir)) {
            return null;
        }

        // Si ya existe pero está vacío o corrupto, se re-descarga.
        if (is_file($target) && filesize($target) > 100000) {
            return $target;
        }

        try {
            $response = \Illuminate\Support\Facades\Http::withOptions([
                'verify'    => false,
                'timeout'   => 60,
                'connect_timeout' => 15,
            ])->get('https://curl.se/ca/cacert.pem');

            if (! $response->successful() || strlen($response->body()) < 100000) {
                return null;
            }

            file_put_contents($target, $response->body());

            Log::info('Scraper: CA bundle descargado', ['path' => $target]);

            return $target;
        } catch (\Throwable $e) {
            Log::warning('Scraper: no se pudo descargar el CA bundle', ['error' => $e->getMessage()]);

            return null;
        }
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Genera los íconos PWA (icon-192.png y icon-512.png) a partir de un SVG
 * con las iniciales "TR" (Tecno-Rexs) sobre el color de marca.
 *
 * Si ya tenés íconos propios, ponelos manualmente en /public/icons/ y este
 * comando no se ejecutará (los archivos existentes no se sobreescriben).
 *
 * Uso: php artisan pwa:generate-icons
 */
class GeneratePwaIcons extends Command
{
    protected $signature = 'pwa:generate-icons {--force : Sobreescribir íconos existentes}';

    protected $description = 'Genera los íconos PWA (192 y 512) en /public/icons/';

    public function handle(): int
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->error('La extensión GD de PHP no está instalada. Habilitá extension=gd en php.ini');
            return self::FAILURE;
        }

        $iconDir = public_path('icons');
        if (! is_dir($iconDir)) {
            mkdir($iconDir, 0755, true);
        }

        $color = [15, 23, 42]; // #0f172a (slate-900, mismo que theme_color)
        $accent = [99, 102, 241]; // #6366f1 (indigo-500)

        $sizes = [192, 512];
        $generated = 0;

        foreach ($sizes as $size) {
            $path = "{$iconDir}/icon-{$size}.png";
            if (file_exists($path) && ! $this->option('force')) {
                $this->warn("  · icon-{$size}.png ya existe (usá --force para sobreescribir)");
                continue;
            }

            $img = imagecreatetruecolor($size, $size);

            // Fondo con el color de marca
            $bg = imagecolorallocate($img, $color[0], $color[1], $color[2]);
            imagefill($img, 0, 0, $bg);

            // Cuadrado de acento en la esquina superior izquierda
            $accentColor = imagecolorallocate($img, $accent[0], $accent[1], $accent[2]);
            $squareSize = (int) ($size * 0.35);
            $squareMargin = (int) ($size * 0.12);
            imagefilledrectangle(
                $img,
                $squareMargin,
                $squareMargin,
                $squareMargin + $squareSize,
                $squareMargin + $squareSize,
                $accentColor
            );

            // Texto "TR" centrado
            $textColor = imagecolorallocate($img, 255, 255, 255);
            $fontSize = max(5, (int) ($size / 11));
            $text = 'TR';
            $textWidth = imagefontwidth($fontSize) * strlen($text);
            $textHeight = imagefontheight($fontSize);
            $x = (int) (($size - $textWidth) / 2);
            $y = (int) (($size - $textHeight) / 2) + (int) ($size * 0.15);
            imagestring($img, $fontSize, $x, $y, $text, $textColor);

            // Tagline "Tecno-Rexs" debajo
            $tagFont = max(3, (int) ($size / 38));
            $tag = 'Tecno-Rexs';
            $tagWidth = imagefontwidth($tagFont) * strlen($tag);
            $tx = (int) (($size - $tagWidth) / 2);
            $ty = $y + $textHeight + (int) ($size * 0.04);
            imagestring($img, $tagFont, $tx, $ty, $tag, $textColor);

            imagepng($img, $path, 9);
            imagedestroy($img);
            $generated++;
            $this->info("  ✓ Generado icon-{$size}.png ({$size}x{$size}, " . filesize($path) . " bytes)");
        }

        $this->newLine();
        if ($generated > 0) {
            $this->info("✅ {$generated} ícono(s) generado(s) en /public/icons/");
        } else {
            $this->info("✅ Todos los íconos ya existen (usá --force para regenerar)");
        }
        $this->line('💡 Si querés íconos con tu logo real, reemplazá los PNG manualmente.');

        return self::SUCCESS;
    }
}

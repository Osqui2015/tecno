<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Amplía la columna `image` a `TEXT` en `products`.
 *
 * Las URLs del CDN de Tiendanube (`dcdn-us.mitiendanube.com`) concatenan
 * múltiples hashes cuando una imagen es reemplazada y superan los 255
 * caracteres (ej. ~260 chars o más), provocando el error MySQL:
 *   SQLSTATE[22001]: String data, right truncated: 1406 Data too long for column 'image'
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->text('image')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('image', 255)->nullable()->change();
        });
    }
};

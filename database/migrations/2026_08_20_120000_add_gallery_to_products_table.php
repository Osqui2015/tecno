<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega `gallery` (JSON) a products.
 *
 * Almacena la galería completa de imágenes del producto scrapeada desde
 * Tiendanube (u otro origen). Cada item es una URL absoluta.
 *
 * Ej:
 *   [
 *     "https://dcdn-us.mitiendanube.com/.../producto-1024-1024.jpg",
 *     "https://dcdn-us.mitiendanube.com/.../producto-2-1024-1024.jpg",
 *     "https://dcdn-us.mitiendanube.com/.../producto-3-1024-1024.jpg"
 *   ]
 *
 * La primera imagen suele ser la principal (mismo valor que `image`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('gallery')->nullable()->after('image');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('gallery');
        });
    }
};

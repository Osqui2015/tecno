<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega la columna `origin` a `products` para distinguir el origen de los
 * productos scrapeados (daz, tuc, manual, etc.).
 *
 * El campo se backfillea automáticamente a partir de `source_url` para los
 * productos ya existentes (Daz o Tuc). Los productos manuales quedan con
 * `origin = NULL`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('origin', 20)->nullable()->after('external_id');
            $table->index('origin');
        });

        // Backfill por dominio de source_url
        DB::statement("UPDATE products SET origin = 'daz' WHERE source_url LIKE '%dazimportadora.com.ar%' AND origin IS NULL");
        DB::statement("UPDATE products SET origin = 'tuc' WHERE source_url LIKE '%tustecnologiastuc.com%' AND origin IS NULL");
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['origin']);
            $table->dropColumn('origin');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega `last_updated_at` a products:
 *  - timestamp de la ÚLTIMA actualización de cualquier campo (admin o scraper).
 *  - Distinto de `last_seen_at` (que solo refleja la última vez que el scraper vio
 *    el producto en el proveedor).
 *  - Distinto de `missing_since` (que se setea cuando el producto desapareció del origen).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('last_updated_at')
                ->nullable()
                ->after('last_seen_at')
                ->comment('Última vez que se modificó cualquier campo del producto');

            $table->index('last_updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['last_updated_at']);
            $table->dropColumn('last_updated_at');
        });
    }
};

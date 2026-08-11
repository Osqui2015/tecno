<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `markup_percent` es el % que se SUMA al precio base (Daz).
 * Default 0 = no hay markup.
 *
 * Ej: producto.price = 1000 (Daz), markup_percent = 25
 *     → final_price = 1000 * 1.25 = 1250 (lo que ve el cliente)
 *
 * El scraper de Daz NO toca este campo; solo actualiza `price`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('markup_percent', 5, 2)->default(0)->after('cash_price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('markup_percent');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campos necesarios para el scraper de dazimportadora.com.ar:
     *  - external_id          → data-id de WooCommerce (identificador único origen)
     *  - sku                  → SKU del producto
     *  - list_price           → precio de lista (precio "normal" tachado)
     *  - cash_price           → precio en efectivo (precio destacado)
     *  - brand                → marca del producto (ej: Novatix, Samsung)
     *  - source_url           → URL original al producto
     *  - categories_external  → array JSON con los nombres de categorías origen
     *  - last_seen_at         → última vez que fue visto en el scraping (auditoría)
     *  - missing_since        → desde cuándo NO aparece (para mantener oculto)
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('external_id')->nullable()->unique()->after('id');
            $table->string('sku')->nullable()->unique()->after('external_id');
            $table->decimal('list_price', 10, 2)->nullable()->after('price');
            $table->decimal('cash_price', 10, 2)->nullable()->after('list_price');
            $table->string('brand')->nullable()->after('description');
            $table->string('source_url', 1000)->nullable()->after('brand');
            $table->json('categories_external')->nullable()->after('source_url');
            $table->timestamp('last_seen_at')->nullable()->after('categories_external');
            $table->timestamp('missing_since')->nullable()->after('last_seen_at');

            $table->index('brand');
            $table->index('sku');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['brand']);
            $table->dropIndex(['sku']);

            $table->dropColumn([
                'external_id',
                'sku',
                'list_price',
                'cash_price',
                'brand',
                'source_url',
                'categories_external',
                'last_seen_at',
                'missing_since',
            ]);
        });
    }
};

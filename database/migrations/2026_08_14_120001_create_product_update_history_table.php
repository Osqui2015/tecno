<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de actualizaciones de productos.
 *
 * Cada vez que un producto se crea o modifica (cualquier campo), se inserta
 * un registro acá con:
 *  - source: quién originó el cambio
 *      · 'admin'        → un usuario desde el panel admin
 *      · 'scraper:daz'  → scraper de dazimportadora
 *      · 'scraper:tuc'  → scraper de tustecnologiatuc
 *      · 'order'        → un pedido modificó el stock
 *      · 'system'       → otro evento interno (ej. ocultado por missing)
 *  - changed_fields: JSON con la lista de campos que cambiaron.
 *  - changes: JSON con { campo: { before, after } } para los campos cambiados.
 *  - actor_id: usuario que disparó el cambio (null si fue el scraper).
 *
 * Esta tabla es APARTE de `product_stock_history` (que es solo cambios de stock
 * y es lo que usa el sistema de "stock bajo" para detectar reposiciones).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_update_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('source', 32); // admin | scraper:daz | scraper:tuc | order | system
            $table->string('event', 32)
                ->default('updated')
                ->comment('updated | created | activated | deactivated');
            $table->json('changed_fields')->nullable();
            $table->json('changes')->nullable();
            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('reference', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Búsquedas típicas: "historial de este producto" ordenado desc.
            $table->index(['product_id', 'created_at']);
            $table->index('source');
            $table->index('actor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_update_history');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carrito persistido en backend (en vez de localStorage).
 * - 1 user tiene N items
 * - 1 product puede estar en N carritos
 * - UNIQUE (user_id, product_id) para que no haya duplicados del mismo producto
 *   en el carrito de un user; si el user "agrega" uno que ya está, se incrementa qty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();
            $table->unsignedInteger('qty')->default(1);
            $table->timestamps();

            // Un mismo producto aparece UNA vez por carrito; sumar en qty.
            $table->unique(['user_id', 'product_id']);
            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};

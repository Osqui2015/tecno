<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campos para que el admin marque qué productos del pedido están
     * disponibles antes de confirmar y armar el mensaje de WhatsApp.
     *
     * - confirmed_available: bool nullable.
     *     · true  → el producto está disponible, se incluirá en el mensaje.
     *     · false → el producto NO está disponible, se listará aparte.
     *     · null  → aún no revisado por el admin.
     *
     * - confirmed_qty: int nullable. Si pidió 3 y solo hay 2, el admin
     *   puede registrar 2. Default = qty original si está disponible.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->boolean('confirmed_available')->nullable()->after('price');
            $table->unsignedInteger('confirmed_qty')->nullable()->after('confirmed_available');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['confirmed_available', 'confirmed_qty']);
        });
    }
};

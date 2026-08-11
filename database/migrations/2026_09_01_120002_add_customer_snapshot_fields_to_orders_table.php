<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot de los datos de envío del cliente al momento de la compra.
 * Nullable para mantener compatibilidad con pedidos ya existentes.
 *
 * ¿Por qué duplicar lo que ya está en users?
 *  - El user puede actualizar su perfil después y los pedidos viejos deben
 *    mostrar la dirección que se usó cuando se hicieron.
 *  - Si en el futuro se permite checkout de invitados, también funciona.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('customer_name')->nullable()->after('user_id');
            $table->string('customer_lastname')->nullable()->after('customer_name');
            $table->string('customer_phone', 30)->nullable()->after('customer_lastname');
            $table->string('customer_address')->nullable()->after('customer_phone');
            $table->string('customer_city')->nullable()->after('customer_address');
            $table->string('customer_zip', 20)->nullable()->after('customer_city');
            $table->text('customer_notes')->nullable()->after('customer_zip');

            // shipping_address queda como resumen principal (compatibilidad)
            // y se sigue usando como "dirección formateada en una línea".
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'customer_name',
                'customer_lastname',
                'customer_phone',
                'customer_address',
                'customer_city',
                'customer_zip',
                'customer_notes',
            ]);
        });
    }
};

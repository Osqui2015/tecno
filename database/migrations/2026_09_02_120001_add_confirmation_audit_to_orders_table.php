<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Auditoría de confirmación del pedido por el admin.
     *
     * - confirmed_at: cuándo el admin terminó de revisar disponibilidad.
     * - confirmed_by: usuario admin que confirmó.
     * - whatsapp_last_sent_at: cuándo se generó/envió el último WhatsApp.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable()->after('status');
            $table->foreignId('confirmed_by')->nullable()->after('confirmed_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('whatsapp_last_sent_at')->nullable()->after('confirmed_by');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['confirmed_by']);
            $table->dropColumn(['confirmed_at', 'confirmed_by', 'whatsapp_last_sent_at']);
        });
    }
};

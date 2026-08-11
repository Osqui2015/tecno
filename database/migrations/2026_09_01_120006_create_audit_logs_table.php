<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de auditoría.
 * Registra cambios importantes: precios, stock, estados de pedido, etc.
 *
 * `actor_type` y `actor_id` referencian al usuario que hizo el cambio.
 * `subject_type` y `subject_id` referencian la entidad modificada.
 * `meta` JSON con valores antes/después.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action', 50); // 'product.price_updated', 'order.status_changed', etc.
            $table->string('description')->nullable(); // resumen humano
            $table->morphs('subject'); // subject_type, subject_id
            $table->nullableMorphs('actor'); // actor_type, actor_id (null si fue automático)
            $table->json('meta')->nullable(); // { before: {...}, after: {...} }
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['action', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

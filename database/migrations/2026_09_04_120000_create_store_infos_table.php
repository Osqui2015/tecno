<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crea la tabla `store_infos` que guarda los datos editables de la tienda
 * desde el panel admin: WhatsApp, dirección, redes sociales, etc.
 *
 * Se modela como singleton: siempre hay un único registro (id=1) que es
 * la fuente de verdad. El config('store.*') se sincroniza con esta tabla
 * en AppServiceProvider, así el resto del código no necesita cambios.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_infos', function (Blueprint $table) {
            $table->id();

            // Datos básicos
            $table->string('name')->default('Tecno-Rexs');
            $table->string('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('whatsapp_number', 30)->nullable();

            // Redes sociales
            $table->string('instagram_url')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('tiktok_url')->nullable();

            // Extras
            $table->string('email_contact')->nullable();
            $table->string('schedule')->nullable();     // ej "Lun-Vie 9-18, Sáb 9-13"
            $table->text('short_description')->nullable();
            $table->decimal('min_purchase', 12, 2)->default(50000);

            $table->timestamps();
        });

        // Sembrar el registro inicial con los defaults del config actual.
        DB::table('store_infos')->insert([
            'name'             => config('store.name', 'Tecno-Rexs'),
            'address'          => config('store.address'),
            'phone'            => config('store.phone'),
            'whatsapp_number'  => config('store.whatsapp_number'),
            'min_purchase'     => config('store.min_purchase', 50000),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('store_infos');
    }
};

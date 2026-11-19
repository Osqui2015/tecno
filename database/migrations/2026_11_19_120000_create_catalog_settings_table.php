<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configuración de visibilidad del catálogo.
 *
 * Permite elegir, por proveedor (daz / tuc / tero / manual), qué categorías
 * se muestran en el catálogo público. Lo que no se selecciona queda oculto.
 *
 * La tabla es "singleton" por proveedor: cada fila describe la visibilidad
 * de un proveedor y sus categorías permitidas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_settings', function (Blueprint $table) {
            $table->id();

            // Origen del proveedor: daz | tuc | tero | manual
            $table->string('origin', 20)->unique();

            // Si está enabled = false, NO se muestra ningún producto de este origen.
            $table->boolean('enabled')->default(true);

            // IDs de categorías (de la tabla `categories`) habilitadas para este origen.
            // NULL = todas las categorías habilitadas (comportamiento por defecto).
            $table->json('category_ids')->nullable();

            // Si está en true, solo se muestran productos de las categorías seleccionadas
            // (productos sin categoría asignada quedan ocultos).
            $table->boolean('filter_by_category')->default(false);

            // Orden de presentación en el catálogo (menor = primero).
            $table->integer('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_settings');
    }
};

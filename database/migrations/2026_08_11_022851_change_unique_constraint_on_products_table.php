<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Intentar borrar el índice único por nombre
        try {
            Schema::table('products', function (Blueprint $table) {
                $table->dropUnique('products_external_id_unique');
            });
        } catch (\Throwable $e) {
            // Ignorar error si no existe
        }

        // 2. Intentar borrar el índice único por array de columnas (fallback)
        try {
            Schema::table('products', function (Blueprint $table) {
                $table->dropUnique(['external_id']);
            });
        } catch (\Throwable $e) {
            // Ignorar error si no existe
        }

        // 3. Crear el nuevo índice compuesto único
        try {
            Schema::table('products', function (Blueprint $table) {
                $table->unique(['external_id', 'origin'], 'products_external_id_origin_unique');
            });
        } catch (\Throwable $e) {
            // Ignorar si ya existe
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('products', function (Blueprint $table) {
                $table->dropUnique('products_external_id_origin_unique');
            });
        } catch (\Throwable $e) {
            // Ignorar error
        }

        try {
            Schema::table('products', function (Blueprint $table) {
                $table->unique('external_id', 'products_external_id_unique');
            });
        } catch (\Throwable $e) {
            // Ignorar error
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $indexes = [];
        try {
            $indexes = Schema::getIndexes('products');
        } catch (\Throwable $e) {
            Log::warning("No se pudieron leer los índices de la tabla products: " . $e->getMessage());
        }

        $indexNames = array_column($indexes, 'name');

        Schema::table('products', function (Blueprint $table) use ($indexNames) {
            $oldIndex = 'products_external_id_unique';
            
            if (in_array($oldIndex, $indexNames)) {
                $table->dropUnique($oldIndex);
            } else {
                try {
                    $table->dropUnique(['external_id']);
                } catch (\Throwable $e) {
                    // Ignorar si no existe
                }
            }

            $newIndex = 'products_external_id_origin_unique';
            if (!in_array($newIndex, $indexNames)) {
                $table->unique(['external_id', 'origin'], $newIndex);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $indexes = [];
        try {
            $indexes = Schema::getIndexes('products');
        } catch (\Throwable $e) {
            Log::warning("No se pudieron leer los índices de la tabla products: " . $e->getMessage());
        }

        $indexNames = array_column($indexes, 'name');

        Schema::table('products', function (Blueprint $table) use ($indexNames) {
            $newIndex = 'products_external_id_origin_unique';
            if (in_array($newIndex, $indexNames)) {
                $table->dropUnique($newIndex);
            }

            $oldIndex = 'products_external_id_unique';
            if (!in_array($oldIndex, $indexNames)) {
                $table->unique('external_id', $oldIndex);
            }
        });
    }
};

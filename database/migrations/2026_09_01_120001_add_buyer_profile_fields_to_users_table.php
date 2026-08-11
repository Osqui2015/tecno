<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos del perfil del comprador.
 * Nullable para no romper registros existentes.
 * Default 'Argentina' en country por la naturaleza del catálogo (Daz es AR).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('lastname')->nullable()->after('name');
            $table->string('phone', 30)->nullable()->after('lastname');
            $table->string('address')->nullable()->after('phone');
            $table->string('city')->nullable()->after('address');
            $table->string('zip_code', 20)->nullable()->after('city');
            $table->string('country')->default('Argentina')->after('zip_code');
            $table->string('document_number', 30)->nullable()->after('country'); // DNI / CUIT / etc.
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'lastname',
                'phone',
                'address',
                'city',
                'zip_code',
                'country',
                'document_number',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega el campo `role` a la tabla users.
     * Default: 'comprador' para que cualquier registro nuevo sea comprador.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Enum-like: 'comprador' | 'admin'. Default seguro para registros nuevos.
            $table->string('role', 20)->default('comprador')->after('password');

            // Índice para acelerar consultas del tipo WHERE role = 'admin'
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }
};

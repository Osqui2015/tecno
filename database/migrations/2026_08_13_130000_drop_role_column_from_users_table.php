<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        // Antes de dropear, nos aseguramos de que TODOS los users tengan
        // un role Spatie equivalente. Si no lo tienen, se lo asignamos.
        // Esto evita perder permisos al dropear la columna.
        $users = DB::table('users')->whereNotNull('role')->get();
        foreach ($users as $u) {
            if (! $u->role) {
                continue;
            }
            $role = Role::firstOrCreate(['name' => $u->role, 'guard_name' => 'web']);
            // Asignar solo si no lo tiene ya (idempotente)
            $exists = DB::table('model_has_roles')
                ->where('model_id', $u->id)
                ->where('model_type', \App\Models\User::class)
                ->where('role_id', $role->id)
                ->exists();
            if (! $exists) {
                DB::table('model_has_roles')->insert([
                    'role_id'    => $role->id,
                    'model_type' => \App\Models\User::class,
                    'model_id'   => $u->id,
                ]);
            }
        }

        // En SQLite, drop column requiere recrear la tabla sin el índice asociado.
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $indexes = DB::select("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='users'");
            foreach ($indexes as $idx) {
                try {
                    DB::statement("DROP INDEX IF EXISTS \"{$idx->name}\"");
                } catch (\Throwable $e) {
                    // continuar
                }
            }
        }

        // Dropeamos la columna solo si existe (idempotente para tests que migran varias veces).
        if (Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 32)->nullable()->after('email');
        });

        // Re-poblar la columna desde Spatie
        $users = DB::table('users')->get();
        foreach ($users as $u) {
            $role = DB::table('model_has_roles')
                ->where('model_id', $u->id)
                ->where('model_type', \App\Models\User::class)
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->value('roles.name');
            if ($role) {
                DB::table('users')->where('id', $u->id)->update(['role' => $role]);
            }
        }
    }
};

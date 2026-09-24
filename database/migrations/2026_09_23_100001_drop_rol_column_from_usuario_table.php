<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE usuario DROP CONSTRAINT IF EXISTS usuario_rol_valido');
        }

        Schema::table('usuario', function (Blueprint $table): void {
            $table->dropColumn('rol');
        });
    }

    public function down(): void
    {
        Schema::table('usuario', function (Blueprint $table): void {
            $table->string('rol', 40)->nullable()->after('telefono');
        });

        $primaryRoleByUser = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->orderBy('role_user.assigned_at')
            ->pluck('roles.slug', 'role_user.user_id');

        foreach ($primaryRoleByUser as $userId => $slug) {
            DB::table('usuario')->where('id_usuario', $userId)->update(['rol' => $slug]);
        }

        DB::table('usuario')->whereNull('rol')->update(['rol' => 'estudiante']);

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE usuario ADD CONSTRAINT usuario_rol_valido CHECK (rol IN ('estudiante', 'docente', 'coordinador_carrera', 'coordinador_titulacion', 'administrador'))");
        }

        Schema::table('usuario', function (Blueprint $table): void {
            $table->string('rol', 40)->nullable(false)->change();
        });
    }
};

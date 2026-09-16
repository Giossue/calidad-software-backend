<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('usuario')) {
            return;
        }

        Schema::create('usuario', function (Blueprint $table): void {
            $table->increments('id_usuario');
            $table->string('cedula', 20)->unique();
            $table->string('nombre', 150);
            $table->string('correo', 150)->unique();
            $table->string('telefono', 20)->nullable();
            $table->string('password_hash');
            $table->string('rol', 40);
            $table->boolean('estado')->default(true);
            $table->timestampsTz();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX usuario_correo_lower_unique ON usuario (lower(correo))');
            DB::statement("ALTER TABLE usuario ADD CONSTRAINT usuario_rol_valido CHECK (rol IN ('estudiante', 'docente', 'coordinador_carrera', 'coordinador_titulacion', 'administrador'))");
            DB::statement("ALTER TABLE usuario ADD CONSTRAINT usuario_cedula_no_vacia CHECK (btrim(cedula) <> '')");
            DB::statement("ALTER TABLE usuario ADD CONSTRAINT usuario_nombre_no_vacio CHECK (btrim(nombre) <> '')");
            DB::statement("ALTER TABLE usuario ADD CONSTRAINT usuario_correo_no_vacio CHECK (btrim(correo) <> '')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario');
    }
};

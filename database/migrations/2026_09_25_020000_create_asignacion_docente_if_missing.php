<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * asignacion_docente forma parte del baseline no versionado;
     * se crea solo si no existe para mantener paridad con producción.
     */
    public function up(): void
    {
        if (! Schema::hasTable('asignacion_docente')) {
            Schema::create('asignacion_docente', function (Blueprint $table): void {
                $table->increments('id_asignacion');
                $table->unsignedInteger('fk_tema_tit');
                $table->unsignedInteger('fk_id_usuario');
                $table->string('rol', 50);
                $table->date('fecha_asignacion')->nullable();
                $table->boolean('estado')->default(true);
                $table->timestampsTz();

                $table->foreign('fk_tema_tit')
                    ->references('id_tema_tit')
                    ->on('tema_titulacion')
                    ->cascadeOnDelete();

                $table->foreign('fk_id_usuario')
                    ->references('id_usuario')
                    ->on('usuario')
                    ->restrictOnDelete();

                $table->unique(['fk_tema_tit', 'fk_id_usuario', 'rol']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asignacion_docente');
    }
};

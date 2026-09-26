<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * usuario_paralelo y tema_titulacion forman parte del baseline no versionado;
     * se crean condicionalmente solo si no existen en el entorno actual.
     */
    public function up(): void
    {
        if (! Schema::hasTable('usuario_paralelo')) {
            Schema::create('usuario_paralelo', function (Blueprint $table): void {
                $table->increments('id_usr_paralelo');
                $table->unsignedInteger('fk_usuario');
                $table->unsignedInteger('fk_paralelo');
                $table->date('fecha_asignacion')->nullable();
                $table->boolean('estado')->default(true);
                $table->timestampsTz();

                $table->foreign('fk_usuario')
                    ->references('id_usuario')
                    ->on('usuario')
                    ->cascadeOnDelete();

                $table->foreign('fk_paralelo')
                    ->references('id_paralelo')
                    ->on('paralelo')
                    ->cascadeOnDelete();

                $table->unique(['fk_usuario', 'fk_paralelo']);
            });
        }

        if (! Schema::hasTable('tema_titulacion')) {
            Schema::create('tema_titulacion', function (Blueprint $table): void {
                $table->increments('id_tema_tit');
                $table->unsignedInteger('fk_id_usuario');
                $table->unsignedInteger('fk_periodo');
                $table->unsignedInteger('fk_coord_revisor')->nullable();
                $table->string('titulo', 255);
                $table->text('descripcion')->nullable();
                $table->string('estado', 50)->default('pendiente');
                $table->date('fecha_propuesta')->nullable();
                $table->date('fecha_revision')->nullable();
                $table->timestampsTz();

                $table->foreign('fk_id_usuario')
                    ->references('id_usuario')
                    ->on('usuario')
                    ->restrictOnDelete();

                $table->foreign('fk_periodo')
                    ->references('id_periodo')
                    ->on('periodo_academico')
                    ->restrictOnDelete();

                $table->foreign('fk_coord_revisor')
                    ->references('id_usuario')
                    ->on('usuario')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tema_titulacion');
        Schema::dropIfExists('usuario_paralelo');
    }
};

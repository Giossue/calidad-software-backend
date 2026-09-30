<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * asignatura_tutoria, inscripcion_tutoria y horario forman parte del baseline;
     * se crean condicionalmente solo si no existen en el entorno actual.
     */
    public function up(): void
    {
        if (! Schema::hasTable('asignatura_tutoria')) {
            Schema::create('asignatura_tutoria', function (Blueprint $table): void {
                $table->increments('id_asig_tutoria');
                $table->unsignedInteger('fk_ciclo')->nullable();
                $table->unsignedInteger('fk_periodo');
                $table->unsignedInteger('fk_modalidad')->nullable();
                $table->unsignedInteger('fk_paralelo')->nullable();
                $table->unsignedInteger('fk_docente')->nullable();
                $table->string('nombre', 150);
                $table->boolean('estado')->default(true);
                $table->timestampsTz();

                $table->foreign('fk_ciclo')
                    ->references('id_ciclo')
                    ->on('ciclo')
                    ->nullOnDelete();

                $table->foreign('fk_periodo')
                    ->references('id_periodo')
                    ->on('periodo_academico')
                    ->restrictOnDelete();

                $table->foreign('fk_modalidad')
                    ->references('id_modalidad')
                    ->on('modalidad')
                    ->nullOnDelete();

                $table->foreign('fk_paralelo')
                    ->references('id_paralelo')
                    ->on('paralelo')
                    ->nullOnDelete();

                $table->foreign('fk_docente')
                    ->references('id_usuario')
                    ->on('usuario')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('inscripcion_tutoria')) {
            Schema::create('inscripcion_tutoria', function (Blueprint $table): void {
                $table->increments('id_inscripcion');
                $table->unsignedInteger('fk_asig_tutoria');
                $table->unsignedInteger('fk_id_usuario');
                $table->date('fecha_inscripcion')->nullable();
                $table->boolean('estado')->default(true);
                $table->timestampsTz();

                $table->foreign('fk_asig_tutoria')
                    ->references('id_asig_tutoria')
                    ->on('asignatura_tutoria')
                    ->cascadeOnDelete();

                $table->foreign('fk_id_usuario')
                    ->references('id_usuario')
                    ->on('usuario')
                    ->cascadeOnDelete();

                $table->unique(['fk_asig_tutoria', 'fk_id_usuario']);
            });
        }

        if (! Schema::hasTable('horario')) {
            Schema::create('horario', function (Blueprint $table): void {
                $table->increments('id_horario');
                $table->unsignedInteger('fk_asig_tutoria');
                $table->string('dia_semana', 50);
                $table->time('hora_inicio');
                $table->time('hora_fin');
                $table->boolean('estado')->default(true);
                $table->timestampsTz();

                $table->foreign('fk_asig_tutoria')
                    ->references('id_asig_tutoria')
                    ->on('asignatura_tutoria')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('horario');
        Schema::dropIfExists('inscripcion_tutoria');
        Schema::dropIfExists('asignatura_tutoria');
    }
};

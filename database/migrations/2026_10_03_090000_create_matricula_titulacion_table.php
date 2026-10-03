<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('matricula_titulacion')) {
            Schema::create('matricula_titulacion', function (Blueprint $table): void {
                $table->increments('id_matricula_tit');
                $table->unsignedInteger('fk_estudiante');
                $table->unsignedInteger('fk_periodo');
                $table->unsignedInteger('fk_carrera')->nullable();
                $table->date('fecha_matricula')->default(now()->toDateString());
                $table->boolean('estado')->default(true);
                $table->timestampsTz();

                $table->foreign('fk_estudiante')
                    ->references('id_usuario')
                    ->on('usuario')
                    ->cascadeOnDelete();

                $table->foreign('fk_periodo')
                    ->references('id_periodo')
                    ->on('periodo_academico')
                    ->cascadeOnDelete();

                $table->foreign('fk_carrera')
                    ->references('id_carrera')
                    ->on('carrera')
                    ->nullOnDelete();

                $table->unique(['fk_estudiante', 'fk_periodo']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('matricula_titulacion');
    }
};

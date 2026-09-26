<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * periodo_academico y periodo_paralelo pertenecen al baseline; se crean
     * solo si todavía no existen para que entornos limpios (tests, local)
     * queden alineados sin romper bases existentes en producción.
     */
    public function up(): void
    {
        if (! Schema::hasTable('periodo_academico')) {
            Schema::create('periodo_academico', function (Blueprint $table): void {
                $table->increments('id_periodo');
                $table->string('nombre', 100)->unique();
                $table->date('fecha_inicio');
                $table->date('fecha_fin');
                $table->boolean('estado')->default(true);
                $table->timestampsTz();
            });
        }

        if (! Schema::hasTable('periodo_paralelo')) {
            Schema::create('periodo_paralelo', function (Blueprint $table): void {
                $table->increments('id_periodo_paralelo');
                $table->unsignedInteger('fk_periodo');
                $table->unsignedInteger('fk_paralelo');
                $table->boolean('estado')->default(true);
                $table->timestampsTz();

                $table->foreign('fk_periodo')
                    ->references('id_periodo')
                    ->on('periodo_academico')
                    ->cascadeOnDelete();

                $table->foreign('fk_paralelo')
                    ->references('id_paralelo')
                    ->on('paralelo')
                    ->cascadeOnDelete();

                $table->unique(['fk_periodo', 'fk_paralelo']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('periodo_paralelo');
    }
};

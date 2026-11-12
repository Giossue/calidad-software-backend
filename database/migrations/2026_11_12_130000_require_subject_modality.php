<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Toda asignatura tiene modalidad: no existe «Sin modalidad».
 *
 * Las asignaturas sin modalidad toman la de su carrera. Si alguna carrera con
 * asignaturas sin modalidad tampoco la tiene, la migración se detiene y la
 * nombra; nunca inventa un valor. Una modalidad en uso ya no puede borrarse.
 */
return new class extends Migration
{
    public function up(): void
    {
        $careers = DB::table('carrera')
            ->whereNull('fk_modalidad')
            ->whereIn('id_carrera', DB::table('subjects')->whereNull('modality_id')->select('career_id'))
            ->orderBy('nombre')
            ->pluck('nombre');
        if ($careers->isNotEmpty()) {
            throw new RuntimeException('Estas carreras no tienen modalidad y sus asignaturas tampoco: '.$careers->implode(', ').'. Registra su modalidad antes de migrar.');
        }

        DB::table('subjects')->whereNull('modality_id')->update([
            'modality_id' => DB::table('carrera')->whereColumn('carrera.id_carrera', 'subjects.career_id')->select('fk_modalidad'),
        ]);

        Schema::table('subjects', function (Blueprint $table): void {
            $table->dropForeign(['modality_id']);
        });
        Schema::table('subjects', function (Blueprint $table): void {
            $table->unsignedInteger('modality_id')->nullable(false)->change();
            $table->foreign('modality_id')->references('id_modalidad')->on('modalidad')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            $table->dropForeign(['modality_id']);
        });
        Schema::table('subjects', function (Blueprint $table): void {
            $table->unsignedInteger('modality_id')->nullable()->change();
            $table->foreign('modality_id')->references('id_modalidad')->on('modalidad')->nullOnDelete();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La malla es la carrera: una malla distinta se registra como otra carrera con su
 * propio código. Se quitan las versiones de la malla (PAO desde el que rige,
 * «hasta qué ciclo», estudiantes fijados a una malla). Si alguna carrera ya tiene
 * una malla además de la inicial, la migración se detiene para no perder datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        $versioned = DB::table('curricula')
            ->join('carrera', 'carrera.id_carrera', '=', 'curricula.career_id')
            ->whereNotNull('curricula.from_period_id')
            ->distinct()
            ->orderBy('carrera.nombre')
            ->pluck('carrera.nombre');
        if ($versioned->isNotEmpty()) {
            throw new RuntimeException('No se pueden quitar las versiones de la malla: estas carreras tienen una malla además de la inicial: '
                .$versioned->implode(', ').'. Registra cada malla nueva como otra carrera y elimina esas mallas antes de migrar.');
        }

        Schema::table('usuario', function (Blueprint $table) {
            $table->dropForeign(['curriculum_id']);
        });
        Schema::table('usuario', function (Blueprint $table) {
            $table->dropColumn('curriculum_id');
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropUnique(['curriculum_id', 'code']);
            $table->dropForeign(['curriculum_id']);
        });
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('curriculum_id');
            $table->unique(['career_id', 'code']);
        });

        Schema::dropIfExists('curricula');
    }

    public function down(): void
    {
        Schema::create('curricula', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('career_id');
            $table->string('name', 150);
            $table->unsignedInteger('from_period_id')->nullable();
            $table->unsignedSmallInteger('up_to_cycle')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestampsTz();
            $table->foreign('career_id')->references('id_carrera')->on('carrera')->restrictOnDelete();
            $table->foreign('from_period_id')->references('id_periodo')->on('periodo_academico')->restrictOnDelete();
            $table->foreign('created_by')->references('id_usuario')->on('usuario')->nullOnDelete();
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->unsignedInteger('curriculum_id')->nullable();
            $table->foreign('curriculum_id')->references('id')->on('curricula')->restrictOnDelete();
        });

        Schema::table('usuario', function (Blueprint $table) {
            $table->unsignedInteger('curriculum_id')->nullable();
            $table->foreign('curriculum_id')->references('id')->on('curricula')->nullOnDelete();
        });

        foreach (DB::table('subjects')->distinct()->pluck('career_id') as $careerId) {
            $curriculumId = DB::table('curricula')->insertGetId([
                'career_id' => $careerId,
                'name' => 'Malla inicial',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('subjects')->where('career_id', $careerId)->update(['curriculum_id' => $curriculumId]);
        }

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropUnique(['career_id', 'code']);
            $table->unique(['curriculum_id', 'code']);
        });
    }
};

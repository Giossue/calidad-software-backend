<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Versiones de la malla curricular de cada carrera. Las asignaturas existentes
 * quedan en la «Malla inicial» de su carrera; una malla nueva rige desde un PAO
 * para los estudiantes que en ese momento estén hasta cierto ciclo.
 */
return new class extends Migration
{
    public function up(): void
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

        // El código se repite entre versiones de la malla: es único dentro de cada malla.
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropUnique(['career_id', 'code']);
            $table->unique(['curriculum_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropUnique(['curriculum_id', 'code']);
            $table->unique(['career_id', 'code']);
            $table->dropForeign(['curriculum_id']);
            $table->dropColumn('curriculum_id');
        });
        Schema::table('usuario', function (Blueprint $table) {
            $table->dropForeign(['curriculum_id']);
            $table->dropColumn('curriculum_id');
        });
        Schema::dropIfExists('curricula');
    }
};

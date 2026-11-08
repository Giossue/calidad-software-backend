<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asignaturas que el estudiante arrastra de ciclos anteriores. Mientras no las
 * apruebe no se le inscribe en las asignaturas que las continúan en la malla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_pending_subjects', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('student_id');
            $table->unsignedInteger('subject_id');
            $table->timestampsTz();

            $table->foreign('student_id')->references('id_usuario')->on('usuario')->cascadeOnDelete();
            $table->foreign('subject_id')->references('id')->on('subjects')->cascadeOnDelete();
            $table->unique(['student_id', 'subject_id']);
            $table->index('subject_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_pending_subjects');
    }
};

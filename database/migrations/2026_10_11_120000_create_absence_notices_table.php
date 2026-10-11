<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avisos de inasistencia que el estudiante envía antes de una clase. Son solo
 * informativos: el docente los ve al tomar lista y decide presente o ausente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absence_notices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('enrollment_id');
            $table->date('date');
            $table->string('reason', 500);
            $table->timestampsTz();

            $table->foreign('enrollment_id')->references('id_inscripcion')->on('inscripcion_tutoria')->cascadeOnDelete();
            $table->unique(['enrollment_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absence_notices');
    }
};

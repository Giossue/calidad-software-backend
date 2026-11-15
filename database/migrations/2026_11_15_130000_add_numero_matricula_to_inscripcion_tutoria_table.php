<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Número de matrícula (1.ª, 2.ª o 3.ª) del estudiante en la asignatura de la tutoría.
 * Desde la 2.ª la asistencia es obligatoria. Las inscripciones existentes quedan en 1.ª.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inscripcion_tutoria', function (Blueprint $table): void {
            $table->unsignedSmallInteger('numero_matricula')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('inscripcion_tutoria', function (Blueprint $table): void {
            $table->dropColumn('numero_matricula');
        });
    }
};

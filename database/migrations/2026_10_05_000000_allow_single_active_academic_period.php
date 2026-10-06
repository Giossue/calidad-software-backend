<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Los PAO vencidos se desactivan igual que lo hará la aplicación.
        DB::table('periodo_academico')
            ->where('estado', true)
            ->whereDate('fecha_fin', '<', now('America/Guayaquil')->toDateString())
            ->update(['estado' => false]);

        $activePeriods = DB::table('periodo_academico')->where('estado', true)->pluck('nombre');

        if ($activePeriods->count() > 1) {
            throw new RuntimeException(
                'Hay más de un período académico activo ('.$activePeriods->implode(', ').'). '
                .'Deje activo solo el PAO vigente antes de aplicar esta migración.'
            );
        }

        DB::statement('CREATE UNIQUE INDEX periodo_academico_single_active_unique ON periodo_academico (estado) WHERE estado = true');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS periodo_academico_single_active_unique');
    }
};

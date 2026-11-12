<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Solo puede haber un período académico vigente. Hasta ahora lo garantizaba el
 * código; este índice único parcial lo garantiza también la base.
 *
 * Antes de crearlo, si hubiera varios períodos activos, se conserva el que está
 * en curso hoy o, si ninguno lo está, el de inicio más reciente (la misma regla
 * de `PeriodoAcademico::sincronizarVigencia`), y los demás quedan inactivos.
 */
return new class extends Migration
{
    private const INDEX = 'periodo_academico_single_active_unique';

    public function up(): void
    {
        $active = DB::table('periodo_academico')->where('estado', true)
            ->orderByDesc('fecha_inicio')->orderByDesc('id_periodo')->get(['id_periodo', 'fecha_inicio', 'fecha_fin']);

        if ($active->count() > 1) {
            $today = now()->toDateString();
            $kept = $active->first(fn (object $period): bool => substr((string) $period->fecha_inicio, 0, 10) <= $today && substr((string) $period->fecha_fin, 0, 10) >= $today)
                ?? $active->first();

            DB::table('periodo_academico')->where('estado', true)->where('id_periodo', '!=', $kept->id_periodo)
                ->update(['estado' => false, 'updated_at' => now()]);
        }

        $condition = DB::getDriverName() === 'pgsql' ? 'estado = true' : 'estado = 1';
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS '.self::INDEX." ON periodo_academico (estado) WHERE {$condition}");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS '.self::INDEX);
    }
};

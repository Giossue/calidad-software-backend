<?php

namespace App\Actions\AcademicPeriods;

use App\Models\PeriodoAcademico;
use Illuminate\Support\Facades\DB;

class CreateAcademicPeriod
{
    /**
     * Un período nuevo queda activo solo si no existe otro PAO activo y su
     * fecha de finalización no ha pasado; en otro caso se registra inactivo.
     */
    public function handle(string $name, string $startDate, string $endDate): PeriodoAcademico
    {
        return DB::transaction(function () use ($name, $startDate, $endDate): PeriodoAcademico {
            $hasActivePeriod = PeriodoAcademico::query()->where('estado', true)->lockForUpdate()->exists();

            return PeriodoAcademico::query()->create([
                'nombre' => $name,
                'fecha_inicio' => $startDate,
                'fecha_fin' => $endDate,
                'estado' => ! $hasActivePeriod && $endDate >= PeriodoAcademico::today(),
            ]);
        });
    }
}

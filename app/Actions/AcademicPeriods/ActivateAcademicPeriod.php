<?php

namespace App\Actions\AcademicPeriods;

use App\Models\PeriodoAcademico;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActivateAcademicPeriod
{
    public function handle(PeriodoAcademico $academicPeriod): PeriodoAcademico
    {
        return DB::transaction(function () use ($academicPeriod): PeriodoAcademico {
            $academicPeriod = PeriodoAcademico::query()->whereKey($academicPeriod->getKey())->lockForUpdate()->firstOrFail();

            if ($academicPeriod->estado) {
                return $academicPeriod;
            }

            if ($academicPeriod->fecha_fin->toDateString() < PeriodoAcademico::today()) {
                throw ValidationException::withMessages([
                    'period' => "El período finalizó el {$academicPeriod->fecha_fin->format('d/m/Y')}; no puede activarse.",
                ]);
            }

            $activePeriod = PeriodoAcademico::query()->where('estado', true)->lockForUpdate()->first();

            if ($activePeriod) {
                throw ValidationException::withMessages([
                    'period' => "Ya existe un período activo ({$activePeriod->nombre}). Solo puede haber un PAO activo; se desactivará automáticamente al finalizar el {$activePeriod->fecha_fin->format('d/m/Y')}.",
                ]);
            }

            $academicPeriod->update(['estado' => true]);

            return $academicPeriod->refresh();
        });
    }
}

<?php

namespace App\Actions\AcademicPeriods;

use App\Models\PeriodoAcademico;

class CreateAcademicPeriod
{
    /** @param array<string, mixed> $data */
    public function handle(array $data): PeriodoAcademico
    {
        $today = now()->toDateString();
        $isCurrent = $data['fecha_inicio'] <= $today && $data['fecha_fin'] >= $today;

        if ($isCurrent) {
            PeriodoAcademico::query()->where('estado', true)->update(['estado' => false]);
            $data['estado'] = true;
        } else {
            $data['estado'] = false;
        }

        $academicPeriod = PeriodoAcademico::query()->create($data);

        PeriodoAcademico::sincronizarVigencia();

        return $academicPeriod->refresh();
    }
}

<?php

namespace App\Actions\AcademicPeriods;

use App\Models\PeriodoAcademico;

class ActivateAcademicPeriod
{
    public function handle(PeriodoAcademico $academicPeriod): PeriodoAcademico
    {
        if (! $academicPeriod->estado) {
            $academicPeriod->update(['estado' => true]);
        }

        return $academicPeriod->refresh();
    }
}

<?php

namespace App\Actions\AcademicPeriods;

use App\Models\PeriodoAcademico;

class DeactivateAcademicPeriod
{
    public function handle(PeriodoAcademico $academicPeriod): PeriodoAcademico
    {
        if ($academicPeriod->estado) {
            $academicPeriod->update(['estado' => false]);
        }

        return $academicPeriod->refresh();
    }
}

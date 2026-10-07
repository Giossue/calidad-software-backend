<?php

namespace App\Actions\Sections;

use App\Models\Paralelo;
use App\Models\PeriodoAcademico;

class RegisterPeriodSection
{
    public function handle(PeriodoAcademico $period, string $name): Paralelo
    {
        /** @var Paralelo $section */
        $section = Paralelo::query()->firstOrCreate(['nombre' => $name], ['estado' => true]);

        if (! $section->estado) {
            $section->update(['estado' => true]);
        }

        $period->paralelos()->syncWithoutDetaching([$section->getKey()]);

        return $section;
    }
}

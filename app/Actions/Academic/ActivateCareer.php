<?php

namespace App\Actions\Academic;

use App\Models\Carrera;

class ActivateCareer
{
    public function handle(Carrera $career): Carrera
    {
        $career->update(['estado' => true]);

        return $career;
    }
}

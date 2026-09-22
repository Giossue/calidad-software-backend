<?php

namespace App\Actions\Academic;

use App\Models\Carrera;

class DeactivateCareer
{
    public function handle(Carrera $career): Carrera
    {
        $career->update(['estado' => false]);

        return $career;
    }
}

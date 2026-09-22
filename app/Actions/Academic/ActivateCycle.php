<?php

namespace App\Actions\Academic;

use App\Models\Ciclo;

class ActivateCycle
{
    public function handle(Ciclo $cycle): Ciclo
    {
        $cycle->update(['estado' => true]);

        return $cycle;
    }
}

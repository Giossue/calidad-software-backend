<?php

namespace App\Actions\Academic;

use App\Models\Ciclo;

class DeactivateCycle
{
    public function handle(Ciclo $cycle): Ciclo
    {
        $cycle->update(['estado' => false]);

        return $cycle;
    }
}

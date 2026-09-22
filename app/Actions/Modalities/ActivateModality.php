<?php

namespace App\Actions\Modalities;

use App\Models\Modalidad;

class ActivateModality
{
    public function handle(Modalidad $modality): Modalidad
    {
        if (! $modality->estado) {
            $modality->update(['estado' => true]);
        }

        return $modality->refresh();
    }
}

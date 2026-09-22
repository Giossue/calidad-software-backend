<?php

namespace App\Actions\Modalities;

use App\Models\Modalidad;

class DeactivateModality
{
    public function handle(Modalidad $modality): Modalidad
    {
        if ($modality->estado) {
            $modality->update(['estado' => false]);
        }

        return $modality->refresh();
    }
}

<?php

namespace App\Actions\Sections;

use App\Models\Paralelo;

class DeactivateSection
{
    public function handle(Paralelo $section): Paralelo
    {
        if ($section->estado) {
            $section->update(['estado' => false]);
        }

        return $section->refresh();
    }
}

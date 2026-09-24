<?php

namespace App\Actions\Sections;

use App\Models\Paralelo;

class ActivateSection
{
    public function handle(Paralelo $section): Paralelo
    {
        if (! $section->estado) {
            $section->update(['estado' => true]);
        }

        return $section->refresh();
    }
}

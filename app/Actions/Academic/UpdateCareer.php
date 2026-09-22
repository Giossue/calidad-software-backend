<?php

namespace App\Actions\Academic;

use App\Models\Carrera;

class UpdateCareer
{
    /** @param array<string, mixed> $attributes */
    public function handle(Carrera $career, array $attributes): Carrera
    {
        $changes = [];

        if (array_key_exists('faculty_id', $attributes)) {
            $changes['fk_facultad'] = (int) $attributes['faculty_id'];
        }

        if (array_key_exists('name', $attributes)) {
            $changes['nombre'] = (string) $attributes['name'];
        }

        $career->fill($changes)->save();

        return $career;
    }
}

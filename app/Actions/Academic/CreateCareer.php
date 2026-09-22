<?php

namespace App\Actions\Academic;

use App\Models\Carrera;

class CreateCareer
{
    /** @param array<string, mixed> $attributes */
    public function handle(array $attributes): Carrera
    {
        return Carrera::query()->create([
            'fk_facultad' => (int) $attributes['faculty_id'],
            'nombre' => (string) $attributes['name'],
            'estado' => true,
        ]);
    }
}

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
            'fk_modalidad' => isset($attributes['modality_id']) ? (int) $attributes['modality_id'] : null,
            'nombre' => (string) $attributes['name'],
            'estado' => true,
        ]);
    }
}

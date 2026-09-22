<?php

namespace App\Actions\Academic;

use App\Models\Ciclo;

class CreateCycle
{
    /** @param array<string, mixed> $attributes */
    public function handle(array $attributes): Ciclo
    {
        return Ciclo::query()->create([
            'fk_carrera' => (int) $attributes['career_id'],
            'nombre' => (string) $attributes['name'],
            'numero' => (int) $attributes['number'],
            'estado' => true,
        ]);
    }
}

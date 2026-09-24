<?php

namespace App\Actions\Academic;

use App\Models\Ciclo;

class UpdateCycle
{
    /** @param array<string, mixed> $attributes */
    public function handle(Ciclo $cycle, array $attributes): Ciclo
    {
        $changes = [];

        if (array_key_exists('career_id', $attributes)) {
            $changes['fk_carrera'] = (int) $attributes['career_id'];
        }

        if (array_key_exists('name', $attributes)) {
            $changes['nombre'] = (string) $attributes['name'];
        }

        if (array_key_exists('number', $attributes)) {
            $changes['numero'] = (int) $attributes['number'];
        }

        if (array_key_exists('paralelo_id', $attributes)) {
            $changes['fk_paralelo'] = $attributes['paralelo_id'] !== null ? (int) $attributes['paralelo_id'] : null;
        }

        $cycle->fill($changes)->save();

        return $cycle;
    }
}

<?php

namespace App\Actions\Academic;

use App\Models\Carrera;
use Illuminate\Support\Facades\DB;

class UpdateCareer
{
    public function __construct(private GenerateCareerCycles $cycles) {}

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

        if (array_key_exists('modality_id', $attributes)) {
            $changes['fk_modalidad'] = $attributes['modality_id'] !== null ? (int) $attributes['modality_id'] : null;
        }

        return DB::transaction(function () use ($career, $changes, $attributes): Carrera {
            $career->fill($changes)->save();

            // Subir la cantidad de ciclos agrega los que faltan; nunca se quitan desde aquí.
            if (isset($attributes['cycles_count'])) {
                $this->cycles->handle($career, (int) $attributes['cycles_count']);
            }

            return $career;
        });
    }
}

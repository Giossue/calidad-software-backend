<?php

namespace App\Actions\Academic;

use App\Models\Carrera;
use Illuminate\Support\Facades\DB;

class CreateCareer
{
    private const DEFAULT_CYCLES = 8;

    public function __construct(private GenerateCareerCycles $cycles) {}

    /** @param array<string, mixed> $attributes */
    public function handle(array $attributes): Carrera
    {
        return DB::transaction(function () use ($attributes): Carrera {
            $career = Carrera::query()->create([
                'fk_facultad' => (int) $attributes['faculty_id'],
                'fk_modalidad' => isset($attributes['modality_id']) ? (int) $attributes['modality_id'] : null,
                'nombre' => (string) $attributes['name'],
                'estado' => true,
            ]);

            $this->cycles->handle($career, (int) ($attributes['cycles_count'] ?? self::DEFAULT_CYCLES));

            return $career;
        });
    }
}

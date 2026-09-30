<?php

namespace App\Actions\Academic;

use App\Models\Carrera;
use App\Models\Ciclo;
use App\Models\Paralelo;
use Illuminate\Support\Facades\DB;

class CreateCareer
{
    private const DEFAULT_CYCLES = 8;

    private const CYCLE_NAMES = [
        1 => 'Primer', 2 => 'Segundo', 3 => 'Tercer', 4 => 'Cuarto', 5 => 'Quinto', 6 => 'Sexto',
        7 => 'Séptimo', 8 => 'Octavo', 9 => 'Noveno', 10 => 'Décimo', 11 => 'Undécimo', 12 => 'Duodécimo',
    ];

    private const DEFAULT_PARALLEL = 'A';

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

            $cyclesCount = (int) ($attributes['cycles_count'] ?? self::DEFAULT_CYCLES);

            if ($cyclesCount === 0) {
                return $career;
            }

            $parallel = Paralelo::query()->firstOrCreate(
                ['nombre' => self::DEFAULT_PARALLEL],
                ['estado' => true],
            );

            for ($number = 1; $number <= $cyclesCount; $number++) {
                Ciclo::query()->create([
                    'fk_carrera' => $career->getKey(),
                    'nombre' => (self::CYCLE_NAMES[$number] ?? $number).' ciclo',
                    'numero' => $number,
                    'fk_paralelo' => $parallel->getKey(),
                    'estado' => true,
                ]);
            }

            return $career;
        });
    }
}

<?php

namespace App\Actions\Academic;

use App\Models\Carrera;
use App\Models\Ciclo;
use App\Models\Paralelo;

/** Crea los ciclos 1..N que falten en una carrera, en el paralelo A. Nunca elimina ni modifica los existentes. */
class GenerateCareerCycles
{
    public const MAX_CYCLES = 12;

    private const DEFAULT_PARALLEL = 'A';

    private const NAMES = [
        1 => 'Primer', 2 => 'Segundo', 3 => 'Tercer', 4 => 'Cuarto', 5 => 'Quinto', 6 => 'Sexto',
        7 => 'Séptimo', 8 => 'Octavo', 9 => 'Noveno', 10 => 'Décimo', 11 => 'Undécimo', 12 => 'Duodécimo',
    ];

    public function handle(Carrera $career, int $count): void
    {
        if ($count < 1) {
            return;
        }

        $parallel = Paralelo::query()->firstOrCreate(['nombre' => self::DEFAULT_PARALLEL], ['estado' => true]);

        for ($number = 1; $number <= min($count, self::MAX_CYCLES); $number++) {
            Ciclo::query()->firstOrCreate(
                ['fk_carrera' => $career->getKey(), 'numero' => $number, 'fk_paralelo' => $parallel->getKey()],
                ['nombre' => (self::NAMES[$number] ?? $number).' ciclo', 'estado' => true],
            );
        }
    }
}

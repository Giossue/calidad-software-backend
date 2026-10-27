<?php

namespace App\Support;

use App\Models\Ciclo;
use App\Models\Usuario;

/**
 * Ciclo y etapa académica del estudiante: el último ciclo de cada carrera es
 * titulación; los anteriores, tutorías.
 */
class StudentCycle
{
    /** @var array<int, int> */
    private array $lastCycleByCareer = [];

    public function lastCycleNumber(int $careerId): int
    {
        return $this->lastCycleByCareer[$careerId] ??= (int) Ciclo::query()->where('fk_carrera', $careerId)->where('estado', true)->max('numero');
    }

    public function isDegreeCycle(Ciclo $cycle): bool
    {
        return $cycle->numero === $this->lastCycleNumber($cycle->fk_carrera);
    }

    /** @return array{cycle_id: int|null, cycle_number: int|null, cycle_name: string|null, section_name: string|null, academic_stage: 'tutorias'|'titulacion'|null} */
    public function describe(Usuario $student): array
    {
        $cycle = $student->ciclo;

        return [
            'cycle_id' => $cycle?->getKey(),
            'cycle_number' => $cycle?->numero,
            'cycle_name' => $cycle?->nombre,
            'section_name' => $cycle?->paralelo?->nombre,
            'academic_stage' => $cycle === null ? null : ($this->isDegreeCycle($cycle) ? 'titulacion' : 'tutorias'),
        ];
    }

    /**
     * Ubica al estudiante en el ciclo y en su paralelo, que es lo que usan las
     * inscripciones en tutorías para ofrecer estudiantes disponibles.
     */
    public function assign(Usuario $student, Ciclo $cycle): void
    {
        $student->update(['fk_ciclo' => $cycle->getKey(), 'fk_carrera' => $cycle->fk_carrera]);

        if ($cycle->fk_paralelo !== null) {
            $student->paralelos()->syncWithoutDetaching([$cycle->fk_paralelo => ['fecha_asignacion' => today(), 'estado' => true]]);
        }
    }
}

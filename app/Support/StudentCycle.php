<?php

namespace App\Support;

use App\Models\Ciclo;
use App\Models\MatriculaTitulacion;
use App\Models\PeriodoAcademico;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;

/**
 * Ciclo y etapa académica del estudiante. Los ciclos anteriores a
 * teaching.degree_from_cycle (6.º por defecto) reciben tutorías; desde ese ciclo
 * el estudiante está en titulación, a la que accede si además está matriculado.
 */
class StudentCycle
{
    public static function degreeFromCycle(): int
    {
        return Config::integer('teaching.degree_from_cycle', 6);
    }

    public function isDegreeCycle(Ciclo $cycle): bool
    {
        return $cycle->numero >= self::degreeFromCycle();
    }

    public function isDegreeStudent(Usuario $student): bool
    {
        return $student->ciclo !== null && $this->isDegreeCycle($student->ciclo);
    }

    /** Titulación exige estar desde el ciclo de titulación y matriculado en un período activo. */
    public function hasDegreeAccess(Usuario $student): bool
    {
        return $this->isDegreeStudent($student)
            && MatriculaTitulacion::query()
                ->where('fk_estudiante', $student->getKey())
                ->where('estado', true)
                ->whereIn('fk_periodo', PeriodoAcademico::query()->where('estado', true)->select('id_periodo'))
                ->exists();
    }

    public function assertCanTakeTutorings(Usuario $student, string $field = 'student_id'): void
    {
        if ($this->isDegreeStudent($student)) {
            throw ValidationException::withMessages([
                $field => 'Desde '.self::degreeFromCycle().'.º ciclo el estudiante está en titulación y no se inscribe en tutorías.',
            ]);
        }
    }

    /**
     * Estudiantes que pueden recibir tutorías: sin ciclo o en un ciclo anterior al de titulación.
     *
     * @param  Builder<Usuario>  $query
     * @return Builder<Usuario>
     */
    public static function scopeTutoringStage(Builder $query): Builder
    {
        return $query->whereDoesntHave('ciclo', fn (Builder $cycle) => $cycle->where('numero', '>=', self::degreeFromCycle()));
    }

    /**
     * Estudiantes en la etapa de titulación.
     *
     * @param  Builder<Usuario>  $query
     * @return Builder<Usuario>
     */
    public static function scopeDegreeStage(Builder $query): Builder
    {
        return $query->whereHas('ciclo', fn (Builder $cycle) => $cycle->where('numero', '>=', self::degreeFromCycle()));
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
        $student->setRelation('ciclo', $cycle);

        if ($cycle->fk_paralelo !== null) {
            $student->paralelos()->syncWithoutDetaching([$cycle->fk_paralelo => ['fecha_asignacion' => today(), 'estado' => true]]);
        }
    }
}

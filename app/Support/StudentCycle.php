<?php

namespace App\Support;

use App\Models\AsignaturaTutoria;
use App\Models\Ciclo;
use App\Models\EstudiantePeriodo;
use App\Models\InscripcionTutoria;
use App\Models\MatriculaTitulacion;
use App\Models\PeriodoAcademico;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
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

    /** Recalcula el ciclo del período de la tutoría de `$enrollment` (alta, rehabilitación o baja). */
    public function syncEnrollment(InscripcionTutoria $enrollment): void
    {
        $periodId = AsignaturaTutoria::query()->whereKey($enrollment->fk_asig_tutoria)->value('fk_periodo');
        $student = Usuario::query()->find($enrollment->fk_id_usuario);
        if ($periodId !== null && $student !== null) {
            $this->syncPeriod($student, (int) $periodId);
        }
    }

    /**
     * Ciclo del estudiante en el período: el número de ciclo más alto entre sus
     * inscripciones activas en tutorías del período, guardado como la fila de
     * ciclo de esas tutorías, que lleva el paralelo (ver `periodCycle`). Si el
     * período es el vigente, `usuario.fk_ciclo` lo sigue.
     *
     * Sin inscripciones activas en el período el registro se elimina: el estudiante
     * no cursó ese período. `usuario.fk_ciclo` se conserva en ese caso.
     */
    public function syncPeriod(Usuario $student, PeriodoAcademico|int $period): ?EstudiantePeriodo
    {
        $period = $period instanceof PeriodoAcademico ? $period : PeriodoAcademico::query()->findOrFail($period);

        $rows = DB::table('inscripcion_tutoria')
            ->join('asignatura_tutoria', 'asignatura_tutoria.id_asig_tutoria', '=', 'inscripcion_tutoria.fk_asig_tutoria')
            ->join('ciclo', 'ciclo.id_ciclo', '=', 'asignatura_tutoria.fk_ciclo')
            ->where('inscripcion_tutoria.fk_id_usuario', $student->getKey())
            ->where('inscripcion_tutoria.estado', true)
            ->where('asignatura_tutoria.fk_periodo', $period->getKey())
            ->get(['ciclo.id_ciclo', 'ciclo.numero', 'ciclo.fk_carrera', 'ciclo.fk_paralelo']);

        if ($rows->isEmpty()) {
            EstudiantePeriodo::query()->where('fk_usuario', $student->getKey())->where('fk_periodo', $period->getKey())->delete();

            return null;
        }

        $cycle = Ciclo::query()->findOrFail($this->periodCycle($rows));

        $record = EstudiantePeriodo::query()->updateOrCreate(
            ['fk_usuario' => $student->getKey(), 'fk_periodo' => $period->getKey()],
            ['fk_ciclo' => $cycle->getKey()],
        );

        if ($period->estado) {
            $this->follow($student, $cycle);
        }

        return $record;
    }

    /**
     * Fila de ciclo del período entre las de las tutorías inscritas (una por
     * inscripción activa): del número más alto, la fila con paralelo que suma más
     * inscripciones y, a igualdad, la de menor id. Si ninguna fila de ese número
     * tiene paralelo, el ciclo base (sin paralelo) de ese número en la carrera o,
     * si no existe, la fila de menor id.
     *
     * @param  Collection<int, object{id_ciclo: int, numero: int, fk_carrera: int, fk_paralelo: int|null}>  $rows
     */
    private function periodCycle(Collection $rows): int
    {
        $top = $rows->max(fn (object $row): int => (int) $row->numero);
        $atTop = $rows->filter(fn (object $row): bool => (int) $row->numero === $top);

        $withSection = $atTop->filter(fn (object $row): bool => $row->fk_paralelo !== null)
            ->countBy(fn (object $row): int => (int) $row->id_ciclo)
            ->map(fn (int $count, int $id): array => [$count, $id])
            ->sort(fn (array $a, array $b): int => [$b[0], $a[1]] <=> [$a[0], $b[1]]);
        if ($withSection->isNotEmpty()) {
            return $withSection->first()[1];
        }

        $lowest = $atTop->sortBy(fn (object $row): int => (int) $row->id_ciclo)->first();

        return (int) (Ciclo::query()->where('fk_carrera', $lowest->fk_carrera)->where('numero', $top)
            ->whereNull('fk_paralelo')->orderByDesc('estado')->orderBy('id_ciclo')->value('id_ciclo') ?? $lowest->id_ciclo);
    }

    /** El período pasó a ser el vigente: el ciclo de cada estudiante sigue su registro del período. */
    public function followActivePeriod(PeriodoAcademico $period): void
    {
        EstudiantePeriodo::query()->where('fk_periodo', $period->getKey())->with('ciclo', 'estudiante.ciclo')->orderBy('id_estudiante_periodo')
            ->each(fn (EstudiantePeriodo $record) => $record->estudiante && $record->ciclo ? $this->follow($record->estudiante, $record->ciclo) : null);
    }

    /**
     * Ciclo de cada período del estudiante, del más reciente al más antiguo.
     *
     * @return list<array{period_id: int, period_name: string|null, cycle_number: int|null, cycle_name: string|null}>
     */
    public function history(Usuario $student): array
    {
        $records = $student->relationLoaded('periodRecords')
            ? $student->periodRecords
            : $student->periodRecords()->with('periodo', 'ciclo')->get();

        return $records
            ->sortByDesc(fn (EstudiantePeriodo $record): string => ($record->periodo?->fecha_inicio?->toDateString() ?? '').sprintf('-%010d', $record->fk_periodo))
            ->map(fn (EstudiantePeriodo $record): array => [
                'period_id' => $record->fk_periodo,
                'period_name' => $record->periodo?->nombre,
                'cycle_number' => $record->ciclo?->numero,
                'cycle_name' => $record->ciclo?->nombre,
            ])->values()->all();
    }

    /** Un estudiante no se inscribe activamente en dos tutorías de la misma asignatura en un período (p. ej. paralelos A y B). */
    public function assertSingleSectionPerSubject(Usuario $student, AsignaturaTutoria $tutoring, string $field = 'student_id'): void
    {
        if ($this->takesSubjectElsewhere($student, $tutoring)) {
            $name = $tutoring->subject?->name ?? $tutoring->nombre;
            throw ValidationException::withMessages([
                $field => "El estudiante ya está inscrito en «{$name}» en otro paralelo de este período.",
            ]);
        }
    }

    public function takesSubjectElsewhere(Usuario $student, AsignaturaTutoria $tutoring): bool
    {
        return $tutoring->subject_id !== null && InscripcionTutoria::query()
            ->where('fk_id_usuario', $student->getKey())
            ->where('estado', true)
            ->where('fk_asig_tutoria', '!=', $tutoring->getKey())
            ->whereHas('asignaturaTutoria', fn (Builder $query) => $query
                ->where('subject_id', $tutoring->subject_id)
                ->where('fk_periodo', $tutoring->fk_periodo))
            ->exists();
    }

    /**
     * Actualiza `usuario.fk_ciclo` a `$cycle` si su número de ciclo o carrera
     * difieren, o si `$cycle` lleva un paralelo distinto. Un ciclo base (sin
     * paralelo) del mismo número no borra el paralelo actual del estudiante.
     */
    private function follow(Usuario $student, Ciclo $cycle): void
    {
        $current = $student->fk_ciclo === null ? null : Ciclo::query()->find($student->fk_ciclo);
        if ($current === null || $current->numero !== $cycle->numero || $current->fk_carrera !== $cycle->fk_carrera
            || ($cycle->fk_paralelo !== null && $current->fk_paralelo !== $cycle->fk_paralelo)) {
            $this->assign($student, $cycle);
        }
    }
}

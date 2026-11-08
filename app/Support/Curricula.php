<?php

namespace App\Support;

use App\Models\AsignaturaTutoria;
use App\Models\Curriculum;
use App\Models\PeriodoAcademico;
use App\Models\Usuario;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Qué malla sigue cada estudiante.
 *
 * - En cada PAO rige la malla más reciente cuyo PAO de inicio ya llegó (la malla
 *   inicial rige siempre que no haya otra).
 * - Al subir una malla nueva, quienes en ese momento están por encima de su ciclo
 *   quedan fijados (`usuario.curriculum_id`) a la malla anterior y terminan la
 *   carrera con ella. El resto, y quienes ingresen después, siguen la que rija en
 *   cada PAO.
 */
class Curricula
{
    /** Malla inicial de la carrera (la crea si aún no existe). */
    public static function base(int $careerId): Curriculum
    {
        return Curriculum::query()->firstOrCreate(['career_id' => $careerId, 'from_period_id' => null], ['name' => 'Malla inicial']);
    }

    /** Malla que rige en la carrera durante el PAO que empieza en `$start` (el PAO activo si es null). */
    public static function inForceAt(int $careerId, ?CarbonInterface $start = null): Curriculum
    {
        $date = ($start ?? PeriodoAcademico::query()->where('estado', true)->orderByDesc('fecha_inicio')->first()->fecha_inicio ?? now())->toDateString();

        return self::ordered($careerId)
            ->filter(fn (Curriculum $curriculum): bool => $curriculum->fromPeriod === null || $curriculum->fromPeriod->fecha_inicio->toDateString() <= $date)
            ->last() ?? self::base($careerId);
    }

    /**
     * Mallas de la carrera de la más antigua a la más reciente.
     *
     * @return EloquentCollection<int, Curriculum>
     */
    public static function ordered(int $careerId): EloquentCollection
    {
        self::base($careerId);

        return Curriculum::query()->where('career_id', $careerId)->with('fromPeriod')->get()
            ->sortBy(fn (Curriculum $curriculum): string => ($curriculum->fromPeriod?->fecha_inicio->toDateString() ?? '0000-00-00').sprintf('-%010d', $curriculum->id))
            ->values();
    }

    /**
     * Malla de la asignatura de la tutoría y malla que rige en el PAO de la tutoría.
     *
     * @return array{int, int}|null
     */
    private static function forTutoring(AsignaturaTutoria $tutoring): ?array
    {
        $tutoring->loadMissing(['subject', 'periodo']);
        $subject = $tutoring->subject;
        if ($subject === null || $subject->curriculum_id === null) {
            return null;
        }

        return [(int) $subject->curriculum_id, self::inForceAt($subject->career_id, $tutoring->periodo?->fecha_inicio)->id];
    }

    /**
     * Solo quienes siguen la malla de la asignatura de la tutoría.
     *
     * @param  Builder<Usuario>  $students
     * @return Builder<Usuario>
     */
    public static function scopeFollows(Builder $students, AsignaturaTutoria $tutoring): Builder
    {
        $ids = self::forTutoring($tutoring);
        if ($ids === null) {
            return $students;
        }
        [$curriculumId, $inForceId] = $ids;

        return $curriculumId === $inForceId
            ? $students->where(fn (Builder $query) => $query->whereNull('usuario.curriculum_id')->orWhere('usuario.curriculum_id', $curriculumId))
            : $students->where('usuario.curriculum_id', $curriculumId);
    }

    public static function follows(Usuario $student, AsignaturaTutoria $tutoring): bool
    {
        $ids = self::forTutoring($tutoring);
        if ($ids === null) {
            return true;
        }
        [$curriculumId, $inForceId] = $ids;

        return ($student->curriculum_id ?? $inForceId) === $curriculumId;
    }

    /**
     * Fija a la malla anterior a quienes no pasan a `$curriculum` (están por encima
     * de su ciclo) y libera a quienes sí pasan para que sigan la que rija.
     */
    public static function assignStudents(Curriculum $curriculum, Curriculum $previous): void
    {
        $students = fn () => Usuario::query()->whereHas('ciclo', fn (Builder $cycle) => $cycle->where('fk_carrera', $curriculum->career_id));

        $students()->whereNull('curriculum_id')
            ->whereHas('ciclo', fn (Builder $cycle) => $cycle->where('numero', '>', $curriculum->up_to_cycle))
            ->update(['curriculum_id' => $previous->id]);
        $students()->whereNotNull('curriculum_id')
            ->whereHas('ciclo', fn (Builder $cycle) => $cycle->where('numero', '<=', $curriculum->up_to_cycle))
            ->update(['curriculum_id' => null]);
    }
}

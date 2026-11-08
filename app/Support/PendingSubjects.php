<?php

namespace App\Support;

use App\Models\AsignaturaTutoria;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Asignaturas que el estudiante arrastra. Quien arrastra una asignatura no puede
 * cursar las que la continúan en la malla (ni las que continúan a esas) hasta
 * aprobarla; el resto de asignaturas de su ciclo sí.
 */
class PendingSubjects
{
    /**
     * Asignaturas que hay que haber aprobado, directa o indirectamente, antes de `$subjectId`.
     *
     * @return Collection<int, int>
     */
    public static function ancestorIds(int $subjectId): Collection
    {
        return self::walk($subjectId, 'next_subject_id', 'subject_id');
    }

    /**
     * Asignaturas que continúan, directa o indirectamente, a las de `$subjectIds`.
     *
     * @param  iterable<int>  $subjectIds
     * @return Collection<int, int>
     */
    public static function descendantIds(iterable $subjectIds): Collection
    {
        return self::walk($subjectIds, 'subject_id', 'next_subject_id');
    }

    /**
     * Excluye a quienes arrastran una previa de la asignatura de la tutoría.
     *
     * @param  Builder<Usuario>  $students
     * @return Builder<Usuario>
     */
    public static function scopeCanTake(Builder $students, AsignaturaTutoria $tutoring): Builder
    {
        if ($tutoring->subject_id === null) {
            return $students;
        }
        $ancestors = self::ancestorIds((int) $tutoring->subject_id);

        return $ancestors->isEmpty()
            ? $students
            : $students->whereDoesntHave('pendingSubjects', fn (Builder $query) => $query->whereIn('subjects.id', $ancestors));
    }

    /**
     * Asignaturas que el estudiante no puede cursar todavía por lo que arrastra.
     *
     * @return Collection<int, int>
     */
    public static function blockedSubjectIds(Usuario $student): Collection
    {
        return self::descendantIds($student->pendingSubjects()->pluck('subjects.id'));
    }

    public static function assertCanTake(Usuario $student, AsignaturaTutoria $tutoring, string $field = 'student_id'): void
    {
        if ($tutoring->subject_id === null) {
            return;
        }
        $pending = $student->pendingSubjects()->whereIn('subjects.id', self::ancestorIds((int) $tutoring->subject_id))->orderBy('subjects.name')->pluck('subjects.name');
        if ($pending->isNotEmpty()) {
            throw ValidationException::withMessages([
                $field => 'El estudiante arrastra '.$pending->map(fn (string $name): string => "«{$name}»")->join(', ', ' y ').', previa de esta asignatura. Podrá inscribirse cuando la apruebe.',
            ]);
        }
    }

    /** Ya aprobó la asignatura: deja de arrastrarla. */
    public static function resolve(Usuario $student, int $subjectId): void
    {
        $student->pendingSubjects()->detach($subjectId);
    }

    /**
     * Recorre la secuencia de la malla a partir de `$start` en un sentido.
     *
     * @param  int|iterable<int>  $start
     * @return Collection<int, int>
     */
    private static function walk(int|iterable $start, string $from, string $to): Collection
    {
        $frontier = collect(is_int($start) ? [$start] : $start)->map(fn ($id): int => (int) $id)->unique()->values();
        $found = collect();
        while ($frontier->isNotEmpty()) {
            $frontier = DB::table('subject_sequences')->whereIn($from, $frontier)->pluck($to)
                ->map(fn ($id): int => (int) $id)
                ->diff($found)
                ->unique()
                ->values();
            $found = $found->merge($frontier);
        }

        return $found->values();
    }
}

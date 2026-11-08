<?php

namespace App\Actions\Tutoring;

use App\Imports\CatalogLookup;
use App\Models\AsignaturaTutoria;
use App\Models\InscripcionTutoria;
use App\Models\Subject;
use App\Models\Usuario;
use App\Support\Curricula;
use App\Support\PendingSubjects;
use App\Support\StudentCycle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Avance por la malla curricular. Quien aprueba todas las asignaturas previas de
 * otra queda inscrito en la tutoría de esa asignatura de un período posterior, en
 * el mismo paralelo en que aprobó. Funciona en los dos sentidos:
 *  - al registrar un aprobado, si la tutoría siguiente ya existe;
 *  - al crear la tutoría siguiente, con quienes aprobaron antes.
 */
class AdvanceApprovedStudents
{
    public function __construct(private StudentCycle $cycles) {}

    /** El docente registró que el estudiante aprobó la tutoría de `$enrollment`. */
    public function afterApproval(InscripcionTutoria $enrollment): void
    {
        $tutoring = $enrollment->asignaturaTutoria()->with(['subject', 'periodo'])->first();
        if ($tutoring?->subject === null || $tutoring->periodo === null) {
            return;
        }
        $student = $enrollment->estudiante;

        // Las que continúan a la aprobada en cualquier versión de la malla (por nombre);
        // solo se inscribe en las de la malla que sigue el estudiante.
        $approvedName = CatalogLookup::normalize($tutoring->subject->name);
        $nextSubjects = Subject::query()->where('career_id', $tutoring->subject->career_id)->whereHas('previousSubjects')->with('previousSubjects:id,name')->get()
            ->filter(fn (Subject $next): bool => $next->previousSubjects->contains(fn (Subject $previous): bool => CatalogLookup::normalize($previous->name) === $approvedName));

        foreach ($nextSubjects as $next) {
            if (! $this->isEligible($student, $next)) {
                continue;
            }
            // Solo hay un período activo a la vez: la tutoría siguiente puede estar ya creada en uno posterior.
            $targets = AsignaturaTutoria::query()->where('estado', true)
                ->where('subject_id', $next->getKey())
                ->where('fk_paralelo', $tutoring->fk_paralelo)
                ->whereHas('periodo', fn (Builder $period) => $period->where('fecha_inicio', '>', $tutoring->periodo->fecha_inicio))
                ->get();
            foreach ($targets as $target) {
                $this->enroll($student, $target);
            }
        }
    }

    /** Se creó o habilitó la tutoría `$tutoring`: inscribe a quienes ya aprobaron sus previas. */
    public function intoNewTutoring(AsignaturaTutoria $tutoring): void
    {
        $tutoring->loadMissing(['subject.previousSubjects', 'periodo']);
        $previousIds = $tutoring->subject?->previousSubjects->pluck('id') ?? collect();
        if ($previousIds->isEmpty() || $tutoring->periodo === null || ! $tutoring->estado) {
            return;
        }
        // Una previa aprobada en otra versión de la malla vale si se llama igual.
        $previousNames = $tutoring->subject->previousSubjects->map(fn (Subject $subject): string => CatalogLookup::normalize($subject->name));
        $previousIds = Subject::query()->where('career_id', $tutoring->subject->career_id)->get(['id', 'name'])
            ->filter(fn (Subject $subject): bool => $previousNames->contains(CatalogLookup::normalize($subject->name)))
            ->pluck('id')->merge($previousIds)->unique()->values();

        $candidateIds = InscripcionTutoria::query()
            ->where('final_result', InscripcionTutoria::APPROVED)
            ->whereHas('asignaturaTutoria', fn (Builder $query) => $query
                ->whereIn('subject_id', $previousIds)
                ->where('fk_paralelo', $tutoring->fk_paralelo)
                ->whereHas('periodo', fn (Builder $period) => $period->where('fecha_inicio', '<', $tutoring->periodo->fecha_inicio)))
            ->distinct()
            ->pluck('fk_id_usuario');

        Usuario::query()->whereKey($candidateIds)->get()
            ->filter(fn (Usuario $student): bool => $this->isEligible($student, $tutoring->subject))
            ->each(fn (Usuario $student) => $this->enroll($student, $tutoring));
    }

    /**
     * Aprobó todas las previas de `$subject` y aún no aprueba `$subject`. Las
     * asignaturas se comparan por nombre: lo aprobado con la malla anterior vale
     * para la nueva.
     */
    private function isEligible(Usuario $student, Subject $subject): bool
    {
        $required = $subject->previousSubjects()->pluck('subjects.name')->map(fn (string $name): string => CatalogLookup::normalize($name));
        if ($required->isEmpty()) {
            return false;
        }
        $approved = $this->approvedSubjectNames($student);

        return $required->diff($approved)->isEmpty() && ! $approved->contains(CatalogLookup::normalize($subject->name));
    }

    /** @return Collection<int, string> */
    private function approvedSubjectNames(Usuario $student): Collection
    {
        return Subject::query()
            ->whereIn('id', AsignaturaTutoria::query()
                ->whereHas('inscripciones', fn (Builder $query) => $query
                    ->where('fk_id_usuario', $student->getKey())
                    ->where('final_result', InscripcionTutoria::APPROVED))
                ->whereNotNull('subject_id')
                ->select('subject_id'))
            ->pluck('name')
            ->map(fn (string $name): string => CatalogLookup::normalize($name))
            ->unique()
            ->values();
    }

    private function enroll(Usuario $student, AsignaturaTutoria $tutoring): void
    {
        if (! $student->estado || ! $student->hasRole('estudiante') || $this->cycles->isDegreeStudent($student)) {
            return;
        }
        if ($tutoring->subject_id !== null && PendingSubjects::blockedSubjectIds($student)->contains((int) $tutoring->subject_id)) {
            return;
        }
        if (! Curricula::follows($student, $tutoring)) {
            return;
        }
        $existing = InscripcionTutoria::query()
            ->where('fk_asig_tutoria', $tutoring->getKey())
            ->where('fk_id_usuario', $student->getKey())
            ->first();
        if ($existing !== null) {
            return;
        }

        if ($tutoring->fk_paralelo && ! $student->paralelos()->whereKey($tutoring->fk_paralelo)->wherePivot('estado', true)->exists()) {
            $student->paralelos()->syncWithoutDetaching([$tutoring->fk_paralelo => ['fecha_asignacion' => today(), 'estado' => true]]);
        }
        InscripcionTutoria::query()->create([
            'fk_asig_tutoria' => $tutoring->getKey(),
            'fk_id_usuario' => $student->getKey(),
            'fecha_inscripcion' => today(),
            'estado' => true,
        ]);
    }
}

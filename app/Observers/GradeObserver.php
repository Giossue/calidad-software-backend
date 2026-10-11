<?php

namespace App\Observers;

use App\Models\Nota;
use App\Support\ActionNotifier;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/** Calificaciones registradas o modificadas por el docente. */
class GradeObserver implements ShouldHandleEventsAfterCommit
{
    public function created(Nota $grade): void
    {
        $this->notify($grade);
    }

    public function updated(Nota $grade): void
    {
        if ($grade->wasChanged('valor')) {
            $this->notify($grade);
        }
    }

    private function notify(Nota $grade): void
    {
        $enrollment = $grade->inscripcion;
        $tutoring = $enrollment?->asignaturaTutoria;
        if ($tutoring === null) {
            return;
        }

        ActionNotifier::send($enrollment->estudiante, 'grades_updated', 'Calificaciones actualizadas',
            "Se registraron calificaciones en la tutoría {$tutoring->nombre}.", '/panel/student-tutorings');
    }
}

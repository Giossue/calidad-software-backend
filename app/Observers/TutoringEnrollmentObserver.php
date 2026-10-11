<?php

namespace App\Observers;

use App\Models\InscripcionTutoria;
use App\Support\ActionNotifier;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/** Inscripción y retiro de estudiantes en una tutoría. */
class TutoringEnrollmentObserver implements ShouldHandleEventsAfterCommit
{
    public function created(InscripcionTutoria $enrollment): void
    {
        if ($enrollment->estado) {
            $this->enrolled($enrollment);
        }
    }

    public function updated(InscripcionTutoria $enrollment): void
    {
        if (! $enrollment->wasChanged('estado')) {
            return;
        }

        $enrollment->estado ? $this->enrolled($enrollment) : $this->withdrawn($enrollment);
    }

    private function enrolled(InscripcionTutoria $enrollment): void
    {
        $tutoring = $enrollment->asignaturaTutoria;
        if ($tutoring === null) {
            return;
        }

        ActionNotifier::send($enrollment->estudiante, 'tutoring_enrolled', 'Fuiste inscrito en una tutoría',
            "Ya formas parte de la tutoría {$tutoring->nombre}.", '/panel/student-tutorings');
        ActionNotifier::send($tutoring->docente, 'tutoring_students_enrolled', 'Nuevos estudiantes en tu tutoría',
            "Se inscribieron estudiantes en la tutoría {$tutoring->nombre}.", '/panel/teacher-tutorings');
    }

    private function withdrawn(InscripcionTutoria $enrollment): void
    {
        $tutoring = $enrollment->asignaturaTutoria;
        if ($tutoring === null) {
            return;
        }

        ActionNotifier::send($tutoring->docente, 'tutoring_students_withdrawn', 'Estudiantes retirados de tu tutoría',
            "Se retiraron estudiantes de la tutoría {$tutoring->nombre}.", '/panel/teacher-tutorings');
    }
}

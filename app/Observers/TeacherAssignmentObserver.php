<?php

namespace App\Observers;

use App\Models\AsignacionDocente;
use App\Support\ActionNotifier;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/** Designación de un docente como tutor o par académico de un tema de titulación. */
class TeacherAssignmentObserver implements ShouldHandleEventsAfterCommit
{
    public function created(AsignacionDocente $assignment): void
    {
        $this->notifyTeacher($assignment);
    }

    public function updated(AsignacionDocente $assignment): void
    {
        if ($assignment->wasChanged('estado')) {
            $this->notifyTeacher($assignment);
        }
    }

    private function notifyTeacher(AsignacionDocente $assignment): void
    {
        $topic = $assignment->temaTitulacion;
        if (! $assignment->estado || $topic === null) {
            return;
        }

        $role = $assignment->rol === 'tutor' ? 'tutor' : 'par académico';
        ActionNotifier::send($assignment->docente, 'degree_assignment', "Fuiste designado {$role}",
            "Tema «{$topic->titulo}» de ".($topic->estudiante?->nombre ?? 'un estudiante').'.', '/panel/teacher-degree-assignments');
    }
}

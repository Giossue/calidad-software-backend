<?php

namespace App\Observers;

use App\Models\AsignaturaTutoria;
use App\Support\ActionNotifier;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/** Asignación de un docente a una tutoría. */
class TutoringObserver implements ShouldHandleEventsAfterCommit
{
    public function updated(AsignaturaTutoria $tutoring): void
    {
        if ($tutoring->wasChanged('fk_docente') && $tutoring->fk_docente !== null) {
            ActionNotifier::send($tutoring->docente, 'tutoring_assigned', 'Se te asignó una tutoría',
                "Ahora eres el docente de la tutoría {$tutoring->nombre}.", '/panel/teacher-tutorings');
        }
    }
}

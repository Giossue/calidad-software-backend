<?php

namespace App\Observers;

use App\Models\MatriculaTitulacion;
use App\Support\ActionNotifier;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/** Matrícula del estudiante en titulación. */
class DegreeEnrollmentObserver implements ShouldHandleEventsAfterCommit
{
    public function created(MatriculaTitulacion $enrollment): void
    {
        $this->notify($enrollment);
    }

    public function updated(MatriculaTitulacion $enrollment): void
    {
        if ($enrollment->wasChanged('estado')) {
            $this->notify($enrollment);
        }
    }

    private function notify(MatriculaTitulacion $enrollment): void
    {
        if ($enrollment->estado) {
            ActionNotifier::send($enrollment->estudiante, 'degree_enrolled', 'Fuiste matriculado en titulación',
                'Ya puedes enviar tu propuesta de tema de titulación.', '/panel/student-degree-topics');
        }
    }
}

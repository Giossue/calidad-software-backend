<?php

namespace App\Observers;

use App\Models\AbsenceNotice;
use App\Support\ActionNotifier;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/** Avisa al docente que un estudiante no asistirá a una clase. */
class AbsenceNoticeObserver implements ShouldHandleEventsAfterCommit
{
    public function created(AbsenceNotice $notice): void
    {
        $enrollment = $notice->enrollment;
        $tutoring = $enrollment?->asignaturaTutoria;
        if ($tutoring === null) {
            return;
        }

        ActionNotifier::send($tutoring->docente, 'absence_notice', 'Aviso de inasistencia',
            ($enrollment->estudiante?->nombre ?? 'Un estudiante')." no podrá asistir a {$tutoring->nombre} el {$notice->date->format('d/m/Y')}. Motivo: {$notice->reason}",
            '/panel/teacher-tutorings');
    }
}

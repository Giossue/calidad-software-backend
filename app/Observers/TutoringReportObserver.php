<?php

namespace App\Observers;

use App\Models\Reporte;
use App\Support\ActionNotifier;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/** Informe de tutoría enviado por el docente a la coordinación de carrera. */
class TutoringReportObserver implements ShouldHandleEventsAfterCommit
{
    public function created(Reporte $report): void
    {
        $tutoring = $report->asignaturaTutoria;
        if ($tutoring === null) {
            return;
        }

        ActionNotifier::send(ActionNotifier::careerCoordinators($tutoring->ciclo?->fk_carrera), 'tutoring_report_sent', 'Nuevo informe de tutoría',
            ($tutoring->docente?->nombre ?? 'Un docente')." envió un informe de la tutoría {$tutoring->nombre}.", '/panel/tutoring-reports');
    }
}

<?php

namespace App\Observers;

use App\Models\InformeTitulacion;
use App\Support\ActionNotifier;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/** Informe o dictamen de seguimiento de titulación. */
class DegreeReportObserver implements ShouldHandleEventsAfterCommit
{
    public function created(InformeTitulacion $report): void
    {
        $topic = $report->ficha?->temaTitulacion;
        if ($topic === null) {
            return;
        }

        ActionNotifier::send($topic->estudiante, 'degree_report_created', 'Nuevo informe de seguimiento',
            "La coordinación registró un informe sobre «{$topic->titulo}».", '/panel/student-degree-tracking');
    }
}

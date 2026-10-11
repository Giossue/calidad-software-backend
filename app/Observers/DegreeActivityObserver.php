<?php

namespace App\Observers;

use App\Models\ActividadAvance;
use App\Support\ActionNotifier;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/** Nueva actividad en la ficha de seguimiento de titulación. */
class DegreeActivityObserver implements ShouldHandleEventsAfterCommit
{
    public function created(ActividadAvance $activity): void
    {
        $topic = $activity->ficha?->temaTitulacion;
        if ($topic === null) {
            return;
        }

        ActionNotifier::send($topic->estudiante, 'degree_activity_created', 'Nueva actividad de seguimiento',
            $activity->descripcion, '/panel/student-degree-tracking');
    }
}

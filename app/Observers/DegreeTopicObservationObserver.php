<?php

namespace App\Observers;

use App\Models\ObservacionTitulacion;
use App\Support\ActionNotifier;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class DegreeTopicObservationObserver implements ShouldHandleEventsAfterCommit
{
    public function created(ObservacionTitulacion $observation): void
    {
        $topic = $observation->temaTitulacion;

        // Al rechazar, la observación ya va incluida en el aviso de rechazo.
        if ($topic === null || $topic->estado === 'rechazado') {
            return;
        }

        ActionNotifier::send($topic->estudiante, 'degree_topic_observation', 'Nueva observación en tu propuesta',
            "La coordinación registró una observación en «{$topic->titulo}».", '/panel/student-degree-topics');
    }
}

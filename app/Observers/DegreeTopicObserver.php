<?php

namespace App\Observers;

use App\Models\TemaTitulacion;
use App\Support\ActionNotifier;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/** Propuestas de titulación: enviada (coordinación) y aprobada o rechazada (estudiante). */
class DegreeTopicObserver implements ShouldHandleEventsAfterCommit
{
    public function created(TemaTitulacion $topic): void
    {
        if ($topic->estado !== 'pendiente') {
            return;
        }

        ActionNotifier::send(
            ActionNotifier::degreeCoordinators(),
            'degree_topic_submitted',
            'Nueva propuesta de titulación',
            ($topic->estudiante?->nombre ?? 'Un estudiante')." envió la propuesta «{$topic->titulo}».",
            '/panel/degree-topics',
        );
    }

    public function updated(TemaTitulacion $topic): void
    {
        if (! $topic->wasChanged('estado')) {
            return;
        }

        match ($topic->estado) {
            'aprobado' => ActionNotifier::send($topic->estudiante, 'degree_topic_approved', 'Tu propuesta fue aprobada',
                "La propuesta «{$topic->titulo}» fue aprobada. Ya puedes consultar a tu tutor y pares académicos.", '/panel/student-degree-assignments'),
            'rechazado' => ActionNotifier::send($topic->estudiante, 'degree_topic_rejected', 'Tu propuesta fue rechazada',
                "La propuesta «{$topic->titulo}» fue rechazada. Revisa las observaciones de la coordinación.", '/panel/student-degree-topics'),
            default => null,
        };
    }
}

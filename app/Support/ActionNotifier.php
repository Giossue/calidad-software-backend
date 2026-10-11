<?php

namespace App\Support;

use App\Models\AsignaturaTutoria;
use App\Models\TemaTitulacion;
use App\Models\Usuario;
use App\Notifications\ActionNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Envía avisos de acciones a los usuarios afectados. Nunca avisa a quien realizó
 * la acción ni a cuentas deshabilitadas, y no repite el mismo aviso a la misma
 * persona dentro de una misma solicitud (p. ej. al guardar varias notas a la vez).
 */
class ActionNotifier
{
    /**
     * @param  Usuario|iterable<Usuario|null>|null  $recipients
     * @param  bool  $includeActor  Avisar también a quien realizó la acción (p. ej. el resultado de su propia carga masiva).
     */
    public static function send(Usuario|iterable|null $recipients, string $kind, string $title, string $body, ?string $url = null, bool $includeActor = false): void
    {
        $actorId = $includeActor ? null : request()->user()?->getKey();
        $sent = request()->attributes->get('action_notifications_sent', []);

        $users = Collection::wrap($recipients instanceof Usuario ? [$recipients] : ($recipients ?? []))
            ->filter(fn (?Usuario $user): bool => $user !== null && $user->estado && $user->getKey() !== $actorId)
            ->unique(fn (Usuario $user): int => $user->getKey())
            ->reject(fn (Usuario $user): bool => isset($sent[self::key($user, $kind, $body)]))
            ->values();

        if ($users->isEmpty()) {
            return;
        }

        foreach ($users as $user) {
            $sent[self::key($user, $kind, $body)] = true;
        }
        request()->attributes->set('action_notifications_sent', $sent);

        Notification::send($users, new ActionNotification($kind, $title, Str::limit($body, 250), $url));
    }

    /** @return Collection<int, Usuario> */
    public static function degreeCoordinators(): Collection
    {
        return Usuario::query()->where('estado', true)
            ->whereHas('roles', fn ($query) => $query->where('slug', 'coordinador_titulacion'))
            ->get();
    }

    /** @return Collection<int, Usuario> */
    public static function careerCoordinators(?int $careerId): Collection
    {
        if ($careerId === null) {
            return new Collection;
        }

        return Usuario::query()->where('estado', true)
            ->whereHas('coordinatedCareers', fn ($query) => $query->where('carrera.id_carrera', $careerId))
            ->get();
    }

    /** Cambio de horario de una tutoría: estudiantes inscritos, docente y coordinación de la carrera. */
    public static function tutoringScheduleChanged(AsignaturaTutoria $tutoring): void
    {
        $students = Usuario::query()->whereIn('id_usuario', $tutoring->inscripciones()->where('estado', true)->select('fk_id_usuario'))->get();
        $body = "Se actualizó el horario de la tutoría {$tutoring->nombre}.";

        self::send($students, 'tutoring_schedule_changed', 'Cambio de horario', $body, '/panel/student-tutorings');
        self::send($tutoring->docente, 'tutoring_schedule_changed', 'Cambio de horario', $body, '/panel/teacher-tutorings');
        self::send(self::careerCoordinators($tutoring->ciclo?->fk_carrera), 'tutoring_schedule_changed', 'Cambio de horario', $body, '/panel/tutorings');
    }

    /** Avance de la ficha de seguimiento de titulación. */
    public static function degreeProgressUpdated(TemaTitulacion $topic, float $percentage): void
    {
        self::send($topic->estudiante, 'degree_progress_updated', 'Avance de titulación actualizado',
            'El avance de «'.$topic->titulo.'» ahora es '.rtrim(rtrim(number_format($percentage, 2, '.', ''), '0'), '.').' %.', '/panel/student-degree-tracking');
    }

    private static function key(Usuario $user, string $kind, string $body): string
    {
        return $user->getKey().'|'.$kind.'|'.$body;
    }
}

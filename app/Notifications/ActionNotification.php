<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Aviso de una acción del sistema que afecta al usuario (no es un mensaje entre
 * personas). Se guarda en la base de datos y se muestra en la campana.
 */
class ActionNotification extends Notification
{
    public function __construct(
        public readonly string $kind,
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $url = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array{kind: string, title: string, body: string, url: string|null} */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
        ];
    }
}

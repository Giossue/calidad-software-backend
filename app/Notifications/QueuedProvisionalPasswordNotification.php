<?php

namespace App\Notifications;

use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Variante encolada para importaciones masivas. Se cifra porque el payload de
 * la cola contiene la contraseña provisional.
 */
class QueuedProvisionalPasswordNotification extends ProvisionalPasswordNotification implements ShouldBeEncrypted, ShouldQueue
{
    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300];
}

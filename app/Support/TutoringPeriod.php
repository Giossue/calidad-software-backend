<?php

namespace App\Support;

use App\Models\AsignaturaTutoria;
use App\Models\PeriodoAcademico;
use Illuminate\Validation\ValidationException;

/**
 * El trabajo diario es del período vigente: las tutorías de otros períodos
 * quedan como historial de solo consulta.
 */
class TutoringPeriod
{
    public const READ_ONLY = 'La tutoría pertenece a un período académico que no está vigente; solo puede consultarse.';

    public static function assertCurrent(AsignaturaTutoria $tutoring, string $field = 'period_id'): void
    {
        if (! PeriodoAcademico::query()->whereKey($tutoring->fk_periodo)->where('estado', true)->exists()) {
            throw ValidationException::withMessages([$field => self::READ_ONLY]);
        }
    }
}

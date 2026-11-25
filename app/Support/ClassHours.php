<?php

namespace App\Support;

use App\Actions\Tutoring\ManageSchedule;
use App\Models\AsignaturaTutoria;
use App\Models\Horario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Hora local de las clases: los horarios se registran en la hora de la universidad
 * (`app.schedule_timezone`), mientras la aplicación trabaja en UTC.
 */
class ClassHours
{
    private const DAYS = [0 => 'domingo', 1 => 'lunes', 2 => 'martes', 3 => 'miercoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sabado'];

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now()->setTimezone((string) config('app.schedule_timezone', 'UTC'));
    }

    public static function today(): string
    {
        return self::now()->toDateString();
    }

    /**
     * Horarios activos de la tutoría en el día de la semana de `$date` (Y-m-d), ordenados por hora.
     *
     * @return Collection<int, Horario>
     */
    public static function schedulesOn(AsignaturaTutoria $tutoring, string $date): Collection
    {
        $day = self::DAYS[CarbonImmutable::parse($date)->dayOfWeek];

        return $tutoring->horarios()->where('estado', true)->orderBy('hora_inicio')->get()
            ->filter(fn (Horario $schedule): bool => ManageSchedule::getDayVariants((string) $schedule->dia_semana)[0] === $day)
            ->values();
    }
}

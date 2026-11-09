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
     * Horarios activos de la tutoría para el día de hoy, ordenados por hora.
     *
     * @return Collection<int, Horario>
     */
    public static function todaySchedules(AsignaturaTutoria $tutoring): Collection
    {
        $today = self::DAYS[self::now()->dayOfWeek];

        return $tutoring->horarios()->where('estado', true)->orderBy('hora_inicio')->get()
            ->filter(fn (Horario $schedule): bool => ManageSchedule::getDayVariants((string) $schedule->dia_semana)[0] === $today)
            ->values();
    }

    /** El horario que está en curso ahora mismo, si hay uno. */
    public static function current(AsignaturaTutoria $tutoring): ?Horario
    {
        $time = self::now()->format('H:i:s');

        return self::todaySchedules($tutoring)->first(
            fn (Horario $schedule): bool => self::time($schedule->hora_inicio) <= $time && $time <= self::time($schedule->hora_fin),
        );
    }

    /** "08:00 a 10:00" */
    public static function label(Horario $schedule): string
    {
        return substr(self::time($schedule->hora_inicio), 0, 5).' a '.substr(self::time($schedule->hora_fin), 0, 5);
    }

    private static function time(mixed $value): string
    {
        $time = (string) $value;

        return strlen($time) === 5 ? $time.':00' : substr($time, 0, 8);
    }
}

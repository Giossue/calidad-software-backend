<?php

namespace App\Support;

use App\Actions\Tutoring\ManageSchedule;
use App\Imports\CatalogLookup;
use App\Models\AsignaturaTutoria;
use App\Models\Horario;
use Illuminate\Support\Collection;

/**
 * Clases conjuntas: el mismo docente dicta la misma asignatura (por nombre) a
 * grupos de distintas carreras o paralelos a la misma hora, por ejemplo Cálculo
 * III para TI y para Software. Su cruce de horario está permitido y el docente
 * toma asistencia y califica a ambos grupos a la vez.
 */
class JointTutorings
{
    public static function sameSubject(?string $a, ?string $b): bool
    {
        return $a !== null && $b !== null && CatalogLookup::normalize($a) === CatalogLookup::normalize($b);
    }

    /**
     * Tutorías activas del mismo docente, período y asignatura que coinciden en
     * horario con `$tutoring`, con los días en que coinciden.
     *
     * @return Collection<int, array{tutoring: AsignaturaTutoria, days: list<string>}>
     */
    public static function for(AsignaturaTutoria $tutoring): Collection
    {
        if (! $tutoring->estado || $tutoring->fk_docente === null) {
            return collect();
        }
        $own = $tutoring->horarios()->where('estado', true)->get();
        if ($own->isEmpty()) {
            return collect();
        }

        return AsignaturaTutoria::query()
            ->where('estado', true)
            ->where('fk_docente', $tutoring->fk_docente)
            ->where('fk_periodo', $tutoring->fk_periodo)
            ->whereKeyNot($tutoring->getKey())
            ->with(['horarios' => fn ($query) => $query->where('estado', true), 'ciclo.carrera', 'paralelo'])
            ->get()
            ->filter(fn (AsignaturaTutoria $other): bool => self::sameSubject($other->nombre, $tutoring->nombre))
            ->map(fn (AsignaturaTutoria $other): array => [
                'tutoring' => $other,
                'days' => $own->flatMap(fn (Horario $mine) => $other->horarios
                    ->filter(fn (Horario $theirs): bool => self::day($theirs->dia_semana) === self::day($mine->dia_semana)
                        && $mine->hora_inicio < $theirs->hora_fin && $theirs->hora_inicio < $mine->hora_fin)
                    ->map(fn (): string => self::day($mine->dia_semana)))
                    ->unique()->values()->all(),
            ])
            ->filter(fn (array $joint): bool => $joint['days'] !== [])
            ->values();
    }

    /** Día sin tildes ni mayúsculas («Miércoles» → «miercoles»). */
    public static function day(string $day): string
    {
        return ManageSchedule::getDayVariants($day)[0];
    }
}

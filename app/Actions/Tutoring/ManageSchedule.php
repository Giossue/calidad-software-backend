<?php

namespace App\Actions\Tutoring;

use App\Models\AsignaturaTutoria;
use App\Models\Horario;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageSchedule
{
    /** @param array{day: string, start_time: string, end_time: string, room: string} $data */
    public function create(AsignaturaTutoria $tutoring, array $data): Horario
    {
        return DB::transaction(function () use ($tutoring, $data): Horario {
            $tutoring = AsignaturaTutoria::query()->whereKey($tutoring->getKey())->lockForUpdate()->firstOrFail();
            $this->validateSchedule($tutoring, $data);

            return Horario::query()->create([
                'fk_asig_tutoria' => $tutoring->getKey(),
                'dia_semana' => $data['day'],
                'hora_inicio' => $data['start_time'].':00',
                'hora_fin' => $data['end_time'].':00',
                'room' => trim($data['room']),
                'estado' => true,
            ]);
        }, 3);
    }

    /** @param array{day?: string, start_time?: string, end_time?: string, room?: string} $data */
    public function update(AsignaturaTutoria $tutoring, Horario $schedule, array $data): Horario
    {
        return DB::transaction(function () use ($tutoring, $schedule, $data): Horario {
            $tutoring = AsignaturaTutoria::query()->whereKey($tutoring->getKey())->lockForUpdate()->firstOrFail();
            $schedule->refresh();
            $merged = [
                'day' => $data['day'] ?? $schedule->dia_semana,
                'start_time' => $data['start_time'] ?? substr($schedule->hora_inicio, 0, 5),
                'end_time' => $data['end_time'] ?? substr($schedule->hora_fin, 0, 5),
                'room' => $data['room'] ?? $schedule->room ?? '',
            ];
            $this->validateSchedule($tutoring, $merged, $schedule->getKey());
            $schedule->update([
                'dia_semana' => $merged['day'],
                'hora_inicio' => $merged['start_time'].':00',
                'hora_fin' => $merged['end_time'].':00',
                'room' => trim($merged['room']),
            ]);

            return $schedule->refresh();
        }, 3);
    }

    /**
     * Reemplaza los horarios activos de la tutoría por la lista indicada (uno
     * por día): desactiva los días retirados, actualiza los existentes y crea
     * los nuevos. Una tutoría siempre conserva al menos un horario.
     *
     * @param  list<array{day: string, start_time: string, end_time: string, room: string|null}>  $schedules
     * @return Collection<int, Horario>
     */
    public function sync(AsignaturaTutoria $tutoring, array $schedules): Collection
    {
        if ($schedules === []) {
            throw ValidationException::withMessages(['schedules' => 'La tutoría debe tener al menos un horario.']);
        }

        return DB::transaction(function () use ($tutoring, $schedules): Collection {
            $locked = AsignaturaTutoria::query()->whereKey($tutoring->getKey())->lockForUpdate()->firstOrFail();
            $existingActive = $locked->horarios()->where('estado', true)->get();
            $newDays = array_column($schedules, 'day');

            foreach ($existingActive as $existing) {
                if (! in_array($existing->dia_semana, $newDays, true)) {
                    $existing->update(['estado' => false]);
                }
            }

            foreach ($schedules as $schedule) {
                $match = $existingActive->firstWhere('dia_semana', $schedule['day']);
                $data = [
                    'day' => $schedule['day'],
                    'start_time' => $schedule['start_time'],
                    'end_time' => $schedule['end_time'],
                    'room' => $schedule['room'] ?? ($match?->room ?: 'Por asignar'),
                ];
                $match ? $this->update($locked, $match, $data) : $this->create($locked, $data);
            }

            return $locked->horarios()->where('estado', true)->orderBy('dia_semana')->orderBy('hora_inicio')->get();
        }, 3);
    }

    /**
     * Desactiva un horario; el horario es obligatorio, así que no permite
     * retirar el último horario activo de la tutoría.
     */
    public function deactivate(AsignaturaTutoria $tutoring, Horario $schedule): Horario
    {
        return DB::transaction(function () use ($tutoring, $schedule): Horario {
            $tutoring = AsignaturaTutoria::query()->whereKey($tutoring->getKey())->lockForUpdate()->firstOrFail();
            $schedule->refresh();
            if ($schedule->estado && $tutoring->horarios()->where('estado', true)->count() <= 1) {
                throw ValidationException::withMessages(['schedule' => 'La tutoría debe conservar al menos un horario activo.']);
            }
            $schedule->update(['estado' => false]);

            return $schedule->refresh();
        });
    }

    /** @param array{day: string, start_time: string, end_time: string, room: string} $data */
    private function validateSchedule(AsignaturaTutoria $tutoring, array $data, ?int $exceptId = null): void
    {
        if (! $tutoring->estado) {
            throw ValidationException::withMessages(['tutoring_id' => 'La tutoría está deshabilitada.']);
        }
        if (trim($data['room']) === '') {
            throw ValidationException::withMessages(['room' => 'El aula es obligatoria.']);
        }
        if ($data['start_time'] >= $data['end_time']) {
            throw ValidationException::withMessages(['end_time' => 'La hora final debe ser posterior a la inicial.']);
        }
        $overlap = $tutoring->horarios()->where('estado', true)->where('dia_semana', $data['day'])
            ->where('hora_inicio', '<', $data['end_time'].':00')
            ->where('hora_fin', '>', $data['start_time'].':00')
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['start_time' => 'El horario se superpone con otro horario activo de esta tutoría.']);
        }

        // Si la tutoría tiene un docente asignado, validar que no tenga conflicto de horario en otra asignatura
        if ($tutoring->fk_docente) {
            // Serializa las escrituras concurrentes sobre los horarios del mismo docente.
            Usuario::query()->whereKey($tutoring->fk_docente)->lockForUpdate()->first();
            $conflict = self::findTeacherScheduleConflict(
                $tutoring->fk_docente,
                $tutoring->fk_periodo,
                $data['day'],
                $data['start_time'],
                $data['end_time'],
                $tutoring->getKey(),
                $exceptId
            );
            if ($conflict) {
                $materia = $conflict->asignaturaTutoria->nombre ?? 'otra asignatura';
                $inicio = substr($conflict->hora_inicio, 0, 5);
                $fin = substr($conflict->hora_fin, 0, 5);
                throw ValidationException::withMessages([
                    'start_time' => "El docente ya está asignado a otra asignatura ({$materia}) en el mismo horario ({$data['day']} de {$inicio} a {$fin}).",
                ]);
            }
        }
    }

    /**
     * Comprueba que los horarios activos de la tutoría no choquen con otra
     * tutoría activa del docente en el período indicado. Debe ejecutarse dentro
     * de una transacción.
     */
    public static function assertTeacherAvailable(AsignaturaTutoria $tutoring, int $teacherId, int $periodId, string $field): void
    {
        Usuario::query()->whereKey($teacherId)->lockForUpdate()->first();

        foreach ($tutoring->horarios()->where('estado', true)->get() as $schedule) {
            $conflict = self::findTeacherScheduleConflict(
                $teacherId,
                $periodId,
                $schedule->dia_semana,
                $schedule->hora_inicio,
                $schedule->hora_fin,
                $tutoring->getKey()
            );
            if ($conflict) {
                $materia = $conflict->asignaturaTutoria->nombre ?? 'otra asignatura';
                $inicio = substr($conflict->hora_inicio, 0, 5);
                $fin = substr($conflict->hora_fin, 0, 5);
                throw ValidationException::withMessages([
                    $field => "El docente ya tiene asignado un horario en '{$materia}' el día {$schedule->dia_semana} de {$inicio} a {$fin}.",
                ]);
            }
        }
    }

    public static function findTeacherScheduleConflict(
        int $teacherId,
        int $periodId,
        string $day,
        string $startTime,
        string $endTime,
        ?int $exceptTutoringId = null,
        ?int $exceptScheduleId = null
    ): ?Horario {
        $startTimeFull = strlen($startTime) === 5 ? $startTime.':00' : $startTime;
        $endTimeFull = strlen($endTime) === 5 ? $endTime.':00' : $endTime;
        $normalizedDay = str_replace(
            ['á', 'é', 'í', 'ó', 'ú'],
            ['a', 'e', 'i', 'o', 'u'],
            trim(mb_strtolower($day))
        );

        return Horario::query()
            ->where('estado', true)
            ->where(fn ($q) => $q->whereRaw('LOWER(dia_semana) = ?', [$normalizedDay])->orWhere('dia_semana', $day))
            ->where('hora_inicio', '<', $endTimeFull)
            ->where('hora_fin', '>', $startTimeFull)
            ->when($exceptScheduleId, fn ($q) => $q->whereKeyNot($exceptScheduleId))
            ->whereHas('asignaturaTutoria', function ($q) use ($teacherId, $periodId, $exceptTutoringId) {
                $q->where('estado', true)
                    ->where('fk_docente', $teacherId)
                    ->where('fk_periodo', $periodId)
                    ->when($exceptTutoringId, fn ($inner) => $inner->where('id_asig_tutoria', '!=', $exceptTutoringId));
            })
            ->with(['asignaturaTutoria'])
            ->first();
    }
}

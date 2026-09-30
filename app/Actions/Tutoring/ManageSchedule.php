<?php

namespace App\Actions\Tutoring;

use App\Models\AsignaturaTutoria;
use App\Models\Horario;
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
    }
}

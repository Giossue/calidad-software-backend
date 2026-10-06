<?php

namespace App\Http\Requests\Api\V1\Concerns;

use Illuminate\Validation\Rule;

/**
 * Reglas y normalización de una lista de horarios semanales (uno por día).
 */
trait ValidatesScheduleList
{
    /** @return array<string, array<int, mixed>> */
    protected function scheduleListRules(): array
    {
        return [
            'schedules' => ['required', 'array', 'min:1'],
            'schedules.*.day' => ['required', 'string', 'distinct', Rule::in(['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'])],
            'schedules.*.start_time' => ['required', 'date_format:H:i'],
            'schedules.*.end_time' => ['required', 'date_format:H:i'],
            'schedules.*.room' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    protected function scheduleListMessages(): array
    {
        return [
            'schedules.required' => 'La tutoría debe tener al menos un horario.',
            'schedules.min' => 'La tutoría debe tener al menos un horario.',
            'schedules.*.day.distinct' => 'No se puede repetir el mismo día en los horarios.',
        ];
    }

    /** @return list<array{day: string, start_time: string, end_time: string, room: string|null}> */
    public function validatedSchedules(): array
    {
        /** @var array<int, array<string, mixed>> $raw */
        $raw = $this->validated('schedules', []);

        return array_values(array_map(fn (array $item) => [
            'day' => (string) $item['day'],
            'start_time' => substr((string) $item['start_time'], 0, 5),
            'end_time' => substr((string) $item['end_time'], 0, 5),
            'room' => isset($item['room']) && trim((string) $item['room']) !== '' ? trim((string) $item['room']) : null,
        ], $raw));
    }
}

<?php

namespace App\Http\Requests\Api\V1\Teacher;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TeacherSyncScheduleRequest extends TeacherMutationRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'schedules' => ['required', 'array', 'min:1'],
            'schedules.*.day' => ['required', 'string', Rule::in(['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'])],
            'schedules.*.start_time' => ['required', 'date_format:H:i'],
            'schedules.*.end_time' => ['required', 'date_format:H:i'],
            'schedules.*.room' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $schedules = $this->input('schedules');
            if (! is_array($schedules)) {
                return;
            }

            $seenDays = [];
            foreach ($schedules as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                if (isset($item['day']) && is_string($item['day'])) {
                    if (in_array($item['day'], $seenDays, true)) {
                        $validator->errors()->add("schedules.{$index}.day", 'No se puede repetir el mismo día en los horarios.');
                    }
                    $seenDays[] = $item['day'];
                }

                if (isset($item['start_time'], $item['end_time']) && is_string($item['start_time']) && is_string($item['end_time'])) {
                    if ($item['start_time'] >= $item['end_time']) {
                        $validator->errors()->add("schedules.{$index}.end_time", 'La hora de fin debe ser posterior a la hora de inicio.');
                    }
                }
            }
        });
    }

    /** @return list<array{day: string, start_time: string, end_time: string, room: string|null}> */
    public function validatedSchedules(): array
    {
        $raw = $this->validated('schedules', []);

        return array_values(array_map(fn (array $item) => [
            'day' => (string) $item['day'],
            'start_time' => substr((string) $item['start_time'], 0, 5),
            'end_time' => substr((string) $item['end_time'], 0, 5),
            'room' => isset($item['room']) && trim((string) $item['room']) !== '' ? trim((string) $item['room']) : null,
        ], $raw));
    }
}

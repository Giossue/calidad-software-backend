<?php

namespace App\Http\Requests\Api\V1\Teacher;

use Illuminate\Validation\Rule;

class TeacherScheduleRequest extends TeacherMutationRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'day' => [$required, 'string', Rule::in(['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'])],
            'start_time' => [$required, 'date_format:H:i'],
            'end_time' => [$required, 'date_format:H:i'],
            'room' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return array{day: string, start_time: string, end_time: string, room: string} */
    public function scheduleData(?string $fallbackRoom = 'Por asignar'): array
    {
        return [
            'day' => $this->string('day')->toString(),
            'start_time' => substr($this->string('start_time')->toString(), 0, 5),
            'end_time' => substr($this->string('end_time')->toString(), 0, 5),
            'room' => $this->filled('room') ? $this->string('room')->trim()->toString() : ($fallbackRoom ?: 'Por asignar'),
        ];
    }
}

<?php

namespace App\Http\Requests\Api\V1\Teacher;

use App\Support\ClassHours;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SessionRequest extends TeacherMutationRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $tutoring = $this->selectedTutoring();

        $periodStart = $tutoring->periodo?->fecha_inicio?->toDateString();
        $periodEnd = $tutoring->periodo?->fecha_fin?->toDateString();

        $dateRules = ['required', 'date_format:Y-m-d', 'before_or_equal:today'];
        if ($periodStart && $periodStart <= now()->toDateString()) {
            $dateRules[] = 'after_or_equal:'.$periodStart;
        }
        if ($periodEnd) {
            $dateRules[] = 'before_or_equal:'.$periodEnd;
        }

        return [
            'date' => $dateRules,
            'topics_covered' => ['required', 'boolean'],
            'topic_ids' => ['exclude_unless:topics_covered,true', 'array', 'max:100'],
            'topic_ids.*' => ['integer', 'distinct', Rule::exists('tema', 'id_tema')->where('fk_asig_tutoria', $tutoring->getKey())],
            'attendance' => ['required', 'array', 'min:1', 'max:500'],
            'attendance.*.enrollment_id' => ['required', 'integer', 'distinct', Rule::exists('inscripcion_tutoria', 'id_inscripcion')->where('fk_asig_tutoria', $tutoring->getKey())->where('estado', true)],
            'attendance.*.present' => ['required', 'boolean'],
        ];
    }

    /**
     * La asistencia solo se toma mientras la tutoría está en curso: hoy (hora local)
     * y dentro de uno de sus horarios activos.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('date') || $validator->errors()->has('date')) {
                return;
            }
            $tutoring = $this->selectedTutoring();
            if (! $tutoring->horarios()->where('estado', true)->exists()) {
                $validator->errors()->add('date', 'Registra el horario de la tutoría antes de tomar asistencia.');

                return;
            }
            if ($this->string('date')->toString() !== ClassHours::today()) {
                $validator->errors()->add('date', 'La asistencia solo se puede tomar el día de la clase, durante su horario.');

                return;
            }
            if (ClassHours::current($tutoring) === null) {
                $today = ClassHours::todaySchedules($tutoring);
                $validator->errors()->add('date', $today->isEmpty()
                    ? 'Hoy no hay clase de esta tutoría: la asistencia solo se puede tomar durante su horario.'
                    : 'La asistencia solo se puede tomar durante el horario de la tutoría (hoy de '.$today->map(fn ($schedule) => ClassHours::label($schedule))->implode(' y de ').').');
            }
        });
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['date' => 'fecha de la sesión', 'topics_covered' => 'temas vistos', 'topic_ids.*' => 'tema', 'attendance' => 'asistencia', 'attendance.*.enrollment_id' => 'estudiante', 'attendance.*.present' => 'asistencia'];
    }

    /** @return array{date: string, topics_covered: bool, topic_ids: array<int, int>, attendance: array<int, array{enrollment_id: int, present: bool}>} */
    public function sessionData(): array
    {
        return [
            'date' => $this->string('date')->toString(), 'topics_covered' => $this->boolean('topics_covered'),
            'topic_ids' => array_map('intval', $this->validated('topic_ids', [])),
            'attendance' => array_map(fn (array $record) => ['enrollment_id' => (int) $record['enrollment_id'], 'present' => (bool) $record['present']], $this->validated('attendance')),
        ];
    }
}

<?php

namespace App\Http\Requests\Api\V1\Teacher;

use Illuminate\Validation\Rule;

class SessionRequest extends TeacherMutationRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $tutoring = $this->selectedTutoring();

        return [
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today',
                'after_or_equal:'.$tutoring->periodo->fecha_inicio->toDateString(),
                'before_or_equal:'.$tutoring->periodo->fecha_fin->toDateString()],
            'topics_covered' => ['required', 'boolean'],
            'topic_ids' => ['exclude_unless:topics_covered,true', 'array', 'max:100'],
            'topic_ids.*' => ['integer', 'distinct', Rule::exists('tema', 'id_tema')->where('fk_asig_tutoria', $tutoring->getKey())],
            'attendance' => ['required', 'array', 'min:1', 'max:500'],
            'attendance.*.enrollment_id' => ['required', 'integer', 'distinct', Rule::exists('inscripcion_tutoria', 'id_inscripcion')->where('fk_asig_tutoria', $tutoring->getKey())->where('estado', true)],
            'attendance.*.present' => ['required', 'boolean'],
        ];
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

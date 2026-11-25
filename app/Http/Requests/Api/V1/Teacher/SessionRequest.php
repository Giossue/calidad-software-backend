<?php

namespace App\Http\Requests\Api\V1\Teacher;

use App\Support\ClassHours;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SessionRequest extends TeacherMutationRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $tutoring = $this->selectedTutoring();

        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'topics_covered' => ['required', 'boolean'],
            'topic_ids' => ['exclude_unless:topics_covered,true', 'array', 'max:100'],
            'topic_ids.*' => ['integer', 'distinct', Rule::exists('tema', 'id_tema')->where('fk_asig_tutoria', $tutoring->getKey())],
            'attendance' => ['required', 'array', 'min:1', 'max:500'],
            'attendance.*.enrollment_id' => ['required', 'integer', 'distinct', Rule::exists('inscripcion_tutoria', 'id_inscripcion')->where('fk_asig_tutoria', $tutoring->getKey())->where('estado', true)->whereNull('abandoned_at')],
            'attendance.*.present' => ['required', 'boolean'],
        ];
    }

    /**
     * La sesión se registra o modifica en cualquier día con clase del período de la
     * tutoría, desde su inicio hasta hoy (hora local de la universidad).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('date') || $validator->errors()->has('date')) {
                return;
            }
            $date = $this->string('date')->toString();
            $tutoring = $this->selectedTutoring();
            if ($date > ClassHours::today()) {
                $validator->errors()->add('date', 'No se pueden registrar sesiones de fechas futuras.');

                return;
            }
            $start = $tutoring->periodo?->fecha_inicio?->toDateString();
            $end = $tutoring->periodo?->fecha_fin?->toDateString();
            if (($start && $date < $start) || ($end && $date > $end)) {
                $validator->errors()->add('date', 'La fecha debe estar dentro del período académico de la tutoría'
                    .($start && $end ? ' (del '.CarbonImmutable::parse($start)->format('d/m/Y').' al '.CarbonImmutable::parse($end)->format('d/m/Y').').' : '.'));

                return;
            }
            if (! $tutoring->horarios()->where('estado', true)->exists()) {
                $validator->errors()->add('date', 'Registra el horario de la tutoría antes de tomar asistencia.');

                return;
            }
            if (ClassHours::schedulesOn($tutoring, $date)->isEmpty()) {
                $validator->errors()->add('date', 'La tutoría no tiene clase ese día.');
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

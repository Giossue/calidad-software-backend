<?php

namespace App\Http\Requests\Api\V1\Teacher;

use Illuminate\Validation\Rule;

class BulkGradesRequest extends TeacherMutationRequest
{
    public const TYPES = ['diagnostic', 'partial', 'partial_two'];

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $tutoring = $this->selectedTutoring();
        $scale = ['nullable', 'numeric', 'decimal:0,2', 'min:'.config('teaching.grade_min'), 'max:'.config('teaching.grade_max')];

        return [
            'grades' => ['required', 'array', 'min:1', 'max:500'],
            'grades.*.enrollment_id' => ['required', 'integer', 'distinct', Rule::exists('inscripcion_tutoria', 'id_inscripcion')->where('fk_asig_tutoria', $tutoring->getKey())],
            'grades.*.diagnostic' => $scale,
            'grades.*.partial' => $scale,
            'grades.*.partial_two' => $scale,
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['grades' => 'calificaciones', 'grades.*.enrollment_id' => 'estudiante', 'grades.*.diagnostic' => 'nota diagnóstica', 'grades.*.partial' => 'parcial 1', 'grades.*.partial_two' => 'parcial 2'];
    }
}

<?php

namespace App\Http\Requests\Api\V1\Teacher;

class FinalPaoReportRequest extends TeacherMutationRequest
{
    /** @return array<string, array<int, mixed>> */
    public static function reportRules(bool $requireTutorings = true): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'report_number' => ['nullable', 'string', 'max:30'],
            'tutoring_ids' => [$requireTutorings ? 'required' : 'sometimes', 'array', 'min:1', 'max:20'],
            'tutoring_ids.*' => ['integer', 'distinct'],
            'observations' => ['nullable', 'array'],
            'observations.*' => ['nullable', 'string', 'max:1000'],
            'conclusions' => ['nullable', 'string', 'max:5000'],
            'recommendations' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<string, string> */
    public static function reportAttributes(): array
    {
        return [
            'date' => 'fecha de informe', 'report_number' => 'número de informe', 'tutoring_ids' => 'asignaturas',
            'observations.*' => 'observación', 'conclusions' => 'conclusiones', 'recommendations' => 'recomendaciones',
        ];
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return self::reportRules();
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return self::reportAttributes();
    }
}

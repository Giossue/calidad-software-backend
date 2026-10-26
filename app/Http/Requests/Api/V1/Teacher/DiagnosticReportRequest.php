<?php

namespace App\Http\Requests\Api\V1\Teacher;

class DiagnosticReportRequest extends TeacherMutationRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'observations' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['date' => 'fecha', 'observations' => 'observaciones'];
    }
}

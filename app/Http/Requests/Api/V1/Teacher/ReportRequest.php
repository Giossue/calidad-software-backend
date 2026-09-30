<?php

namespace App\Http\Requests\Api\V1\Teacher;

class ReportRequest extends TeacherMutationRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'observations' => ['nullable', 'string', 'max:10000'],
        ];
    }
}

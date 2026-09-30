<?php

namespace App\Http\Requests\Api\V1\Teacher;

use App\Models\AsignaturaTutoria;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeacherListRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tutoring = $this->route('tutoring');

        return $tutoring instanceof AsignaturaTutoria
            ? $this->user()->can('viewAssigned', $tutoring)
            : $this->user()->can('viewAssignedAny', AsignaturaTutoria::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:150'], 'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'role' => ['nullable', Rule::in(['tutor', 'par_academico'])], 'date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}

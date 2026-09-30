<?php

namespace App\Http\Requests\Api\V1\Teacher;

class GradeRequest extends TeacherMutationRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['value' => ['required', 'numeric', 'decimal:0,2', 'min:'.config('teaching.grade_min'), 'max:'.config('teaching.grade_max')]];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['value' => 'calificación'];
    }
}

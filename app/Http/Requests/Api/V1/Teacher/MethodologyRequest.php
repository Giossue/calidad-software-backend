<?php

namespace App\Http\Requests\Api\V1\Teacher;

class MethodologyRequest extends TeacherMutationRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['description' => ['required', 'string', 'max:255']];
    }
}

<?php

namespace App\Http\Requests\Api\V1\Teacher;

use App\Models\InscripcionTutoria;
use Illuminate\Validation\Rule;

class FinalResultRequest extends TeacherMutationRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['result' => ['present', 'nullable', Rule::in([InscripcionTutoria::APPROVED, InscripcionTutoria::FAILED])]];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['result' => 'resultado final'];
    }
}

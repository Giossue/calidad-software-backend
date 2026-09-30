<?php

namespace App\Http\Requests\Api\V1\Teacher;

use Illuminate\Validation\Rule;

class TopicRequest extends TeacherMutationRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('tema', 'nombre')->where('fk_asig_tutoria', $this->selectedTutoring()->getKey())->ignore($this->route('topic'))],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}

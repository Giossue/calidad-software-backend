<?php

namespace App\Http\Requests\Api\V1\Teacher;

use Illuminate\Validation\Rule;

class TopicRequest extends TeacherMutationRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $topic = $this->route('topic');

        return [
            'name' => [$topic ? 'sometimes' : 'required', 'string', 'max:150', Rule::unique('tema', 'nombre')->where('fk_asig_tutoria', $this->selectedTutoring()->getKey())->ignore($topic)],
            'description' => ['nullable', 'string', 'max:255'],
            'is_covered' => ['nullable', 'boolean'],
        ];
    }
}

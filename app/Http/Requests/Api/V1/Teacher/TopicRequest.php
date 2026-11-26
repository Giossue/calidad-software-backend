<?php

namespace App\Http\Requests\Api\V1\Teacher;

use App\Http\Requests\Concerns\UppercasesInput;
use Illuminate\Validation\Rule;

class TopicRequest extends TeacherMutationRequest
{
    use UppercasesInput;

    protected function uppercaseFields(): array
    {
        return ['name'];
    }

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

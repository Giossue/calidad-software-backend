<?php

namespace App\Http\Requests\Api\V1\Teacher;

use Illuminate\Validation\Rule;

class ActivityRequest extends TeacherMutationRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('actividad', 'nombre')->where('fk_tema', $this->selectedTopic()->getKey())->ignore($this->route('activity'))],
            'duration' => ['required', 'string', 'max:50'],
        ];
    }
}

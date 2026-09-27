<?php

namespace App\Http\Requests\Api\V1\Tutoring;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignTutoringTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('tutoring'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'teacher_id' => ['required', 'integer', Rule::exists('usuario', 'id_usuario')->where('estado', true)],
        ];
    }
}

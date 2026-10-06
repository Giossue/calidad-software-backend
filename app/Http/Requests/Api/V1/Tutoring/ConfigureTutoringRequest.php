<?php

namespace App\Http\Requests\Api\V1\Tutoring;

use App\Http\Requests\Api\V1\Concerns\ValidatesScheduleList;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfigureTutoringRequest extends FormRequest
{
    use ValidatesScheduleList;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('tutoring'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'teacher_id' => ['required', 'integer', Rule::exists('usuario', 'id_usuario')->where('estado', true)],
            'cycle_id' => ['nullable', 'integer', Rule::exists('ciclo', 'id_ciclo')->where('estado', true)],
            ...$this->scheduleListRules(),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->scheduleListMessages();
    }
}

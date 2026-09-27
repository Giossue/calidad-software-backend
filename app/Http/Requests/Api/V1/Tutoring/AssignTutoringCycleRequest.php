<?php

namespace App\Http\Requests\Api\V1\Tutoring;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignTutoringCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('tutoring'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'cycle_id' => ['required', 'integer', Rule::exists('ciclo', 'id_ciclo')->where('estado', true)],
        ];
    }
}

<?php

namespace App\Http\Requests\Api\V1\Tutoring;

use App\Models\AsignaturaTutoria;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfigureTutoringRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tutoring = $this->route('tutoring');

        return $tutoring instanceof AsignaturaTutoria && $this->user()->can('update', $tutoring);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'teacher_id' => ['required', 'integer', Rule::exists('usuario', 'id_usuario')->where('estado', true)],
            'cycle_id' => ['nullable', 'integer', Rule::exists('ciclo', 'id_ciclo')->where('estado', true)],
            'schedules' => ['present', 'array'],
            'schedules.*.day' => ['required', 'string', Rule::in(['lunes', 'martes', 'miercoles', 'miércoles', 'jueves', 'viernes', 'sabado', 'sábado', 'domingo'])],
            'schedules.*.start_time' => ['required', 'string'],
            'schedules.*.end_time' => ['required', 'string'],
            'schedules.*.room' => ['nullable', 'string', 'max:100'],
        ];
    }
}

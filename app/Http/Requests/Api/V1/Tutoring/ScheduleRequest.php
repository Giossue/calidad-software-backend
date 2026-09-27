<?php

namespace App\Http\Requests\Api\V1\Tutoring;

use App\Models\AsignaturaTutoria;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tutoring = $this->route('tutoring');

        return $tutoring instanceof AsignaturaTutoria && $this->user()->can('update', $tutoring);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'day' => [$required, Rule::in(['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'])],
            'start_time' => [$required, 'date_format:H:i'],
            'end_time' => [$required, 'date_format:H:i'],
            'room' => [$required, 'string', 'max:100'],
        ];
    }
}

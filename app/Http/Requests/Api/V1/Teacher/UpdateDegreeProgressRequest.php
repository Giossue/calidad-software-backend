<?php

namespace App\Http\Requests\Api\V1\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDegreeProgressRequest extends FormRequest
{
    /** La autorización sobre el tema se resuelve en el controlador. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'porcentaje_avance' => ['required', 'numeric', 'min:0', 'max:100'],
            'estado' => ['nullable', 'string', 'max:50'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'porcentaje_avance' => 'porcentaje de avance',
            'estado' => 'estado',
        ];
    }
}

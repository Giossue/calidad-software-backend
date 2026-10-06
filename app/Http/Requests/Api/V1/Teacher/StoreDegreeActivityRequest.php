<?php

namespace App\Http\Requests\Api\V1\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class StoreDegreeActivityRequest extends FormRequest
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
            'descripcion' => ['required', 'string', 'max:1000'],
            'completada' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'descripcion' => 'descripción',
            'completada' => 'completada',
        ];
    }
}

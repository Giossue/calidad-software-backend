<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rules\Unique;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string|In|Password|Unique|ValidationRule>> */
    public function rules(): array
    {
        return [
            'identification' => [
                'sometimes', 'string', 'max:20',
                Rule::unique('usuario', 'cedula')->ignore($this->route('user')),
            ],
            'name' => ['sometimes', 'string', 'max:150'],
            'email' => [
                'sometimes', 'string', 'email:rfc', 'max:150',
                Rule::unique('usuario', 'correo')->ignore($this->route('user')),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'role' => ['sometimes', Rule::in([
                'estudiante',
                'docente',
                'coordinador_carrera',
                'coordinador_titulacion',
                'administrador',
            ])],
            'password' => ['sometimes', 'string', Password::default(), 'confirmed'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'identification' => 'cédula',
            'name' => 'nombre',
            'email' => 'correo electrónico',
            'phone' => 'teléfono',
            'role' => 'rol',
            'password' => 'contraseña',
        ];
    }
}
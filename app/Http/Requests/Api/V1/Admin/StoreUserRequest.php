<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\Usuario;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\Unique;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Usuario::class) ?? false;
    }

    /** @return array<string, array<int, string|In|Unique|ValidationRule>> */
    public function rules(): array
    {
        return [
            'identification' => ['required', 'string', 'max:20', Rule::unique('usuario', 'cedula')],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email:rfc', 'max:150', Rule::unique('usuario', 'correo')],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in([
                'estudiante',
                'docente',
                'coordinador_carrera',
                'coordinador_titulacion',
                'administrador',
            ])],
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
        ];
    }
}

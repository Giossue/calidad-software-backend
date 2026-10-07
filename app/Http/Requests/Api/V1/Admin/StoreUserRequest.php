<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\Role;
use App\Models\Usuario;
use App\Rules\CedulaOPasaporte;
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
            'identification' => ['required', 'string', new CedulaOPasaporte, Rule::unique('usuario', 'cedula')],
            'name' => ['required', 'string', 'max:150', 'regex:/^[\pL\s]+$/u'],
            'email' => ['required', 'string', 'email:rfc', 'max:150', Rule::unique('usuario', 'correo')],
            'phone' => ['required', 'digits:10'],
            'role' => ['required', Rule::in(Role::query()->pluck('slug'))],
            'faculty_id' => ['nullable', 'integer', Rule::exists('facultad', 'id_facultad')],
            'career_id' => ['nullable', 'integer', Rule::exists('carrera', 'id_carrera')],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'identification' => 'cédula o pasaporte',
            'name' => 'nombre',
            'email' => 'correo electrónico',
            'phone' => 'teléfono',
            'role' => 'rol',
            'faculty_id' => 'facultad',
            'career_id' => 'carrera',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.digits' => 'El teléfono debe tener exactamente 10 dígitos numéricos.',
            'name.regex' => 'El nombre solo puede contener letras y espacios.',
        ];
    }
}

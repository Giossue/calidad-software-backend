<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Concerns\CombinesPersonNames;
use App\Models\Role;
use App\Models\Usuario;
use App\Rules\CedulaOPasaporte;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    use CombinesPersonNames;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Usuario::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->preparePersonNames();
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'identification' => ['required', 'string', new CedulaOPasaporte, Rule::unique('usuario', 'cedula')],
            ...$this->personNameRules(),
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
            ...$this->personNameAttributes(),
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
            ...$this->personNameMessages(),
        ];
    }
}

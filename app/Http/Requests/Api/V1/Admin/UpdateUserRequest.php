<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Concerns\CombinesPersonNames;
use App\Models\Role;
use App\Models\Usuario;
use App\Rules\CedulaOPasaporte;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    use CombinesPersonNames;

    public function authorize(): bool
    {
        $user = $this->route('user');

        return $user instanceof Usuario
            && ($this->user()?->can('update', $user) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->preparePersonNames();
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'identification' => [
                'sometimes', 'string', new CedulaOPasaporte,
                Rule::unique('usuario', 'cedula')->ignore($this->route('user')),
            ],
            ...$this->personNameRules(false),
            'email' => [
                'sometimes', 'string', 'email:rfc', 'max:150',
                Rule::unique('usuario', 'correo')->ignore($this->route('user')),
            ],
            'phone' => ['sometimes', 'nullable', 'digits:10'],
            'role' => ['sometimes', Rule::in(Role::query()->pluck('slug'))],
            'faculty_id' => ['nullable', 'integer', Rule::exists('facultad', 'id_facultad')],
            'career_id' => ['nullable', 'integer', Rule::exists('carrera', 'id_carrera')],
            'password' => ['sometimes', 'string', Password::default(), 'confirmed'],
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
            'password' => 'contraseña',
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

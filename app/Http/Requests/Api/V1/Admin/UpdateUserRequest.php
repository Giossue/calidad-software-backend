<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\Role;
use App\Models\Usuario;
use App\Rules\CedulaEcuatoriana;
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
        $user = $this->route('user');

        return $user instanceof Usuario
            && ($this->user()?->can('update', $user) ?? false);
    }

    /** @return array<string, array<int, string|In|Password|Unique|ValidationRule>> */
    public function rules(): array
    {
        return [
            'identification' => [
                'sometimes', 'digits:10', new CedulaEcuatoriana,
                Rule::unique('usuario', 'cedula')->ignore($this->route('user')),
            ],
            'name' => ['sometimes', 'string', 'max:150', 'regex:/^[\pL\s]+$/u'],
            'email' => [
                'sometimes', 'string', 'email:rfc', 'max:150',
                Rule::unique('usuario', 'correo')->ignore($this->route('user')),
            ],
            'phone' => ['sometimes', 'digits:10'],
            'role' => ['sometimes', Rule::in(Role::query()->pluck('slug'))],
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

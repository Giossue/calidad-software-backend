<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Models\Usuario;
use App\Rules\CedulaOPasaporte;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CompleteProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof Usuario && $user->must_complete_profile;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'identification' => strtoupper(trim((string) $this->input('identification'))),
            'name' => trim((string) $this->input('name')),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        /** @var Usuario $user */
        $user = $this->user();

        return [
            'identification' => ['required', 'string', new CedulaOPasaporte, Rule::unique('usuario', 'cedula')->ignore($user)],
            'name' => ['required', 'string', 'max:150', 'regex:/^[\pL\s]+$/u'],
            'phone' => ['required', 'digits:10'],
            'password' => ['required', 'string', Password::default(), 'confirmed',
                function (string $attribute, mixed $value, Closure $fail) use ($user): void {
                    if (is_string($value) && Hash::check($value, $user->password_hash)) {
                        $fail('La nueva contraseña debe ser distinta de la contraseña provisional.');
                    }
                }],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'identification' => 'cédula o pasaporte',
            'name' => 'nombre',
            'phone' => 'teléfono',
            'password' => 'contraseña',
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

    protected function failedAuthorization(): void
    {
        abort(403, 'Tu perfil ya está completo.');
    }
}

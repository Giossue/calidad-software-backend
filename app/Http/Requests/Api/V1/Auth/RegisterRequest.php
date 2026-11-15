<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Support\InstitutionalEmail;
use App\Support\PersonName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class RegisterRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_names' => PersonName::normalize($this->input('first_names')),
            'last_names' => PersonName::normalize($this->input('last_names')),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...Arr::except($this->profileRules(), 'name'),
            ...$this->personNameRules(),
            'email' => ['required', 'string', 'email', 'max:255', 'unique:usuario,correo', InstitutionalEmail::rule()],
            'password' => $this->passwordRules(),
            'password_confirmation' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.ends_with' => InstitutionalEmail::message(),
            'identification.digits' => 'La cédula debe contener exactamente 10 dígitos numéricos.',
            'first_names.regex' => 'Los nombres solo pueden contener letras y espacios.',
            'last_names.regex' => 'Los apellidos solo pueden contener letras y espacios.',
        ];
    }

    /** Nombre completo tal como se guarda: apellidos seguidos de nombres. */
    public function fullName(): ?string
    {
        return PersonName::full($this->validated('last_names'), $this->validated('first_names'));
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'identification' => 'cédula',
            'first_names' => 'nombres',
            'last_names' => 'apellidos',
            'email' => 'correo electrónico',
            'password' => 'contraseña',
            'device_name' => 'nombre del dispositivo',
        ];
    }
}

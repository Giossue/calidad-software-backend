<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->profileRules(),
            'email' => ['required', 'string', 'email', 'max:255', 'unique:usuario,correo', 'ends_with:@ueb.edu.ec'],
            'password' => $this->passwordRules(),
            'password_confirmation' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.ends_with' => 'El correo electrónico debe pertenecer al dominio institucional (@ueb.edu.ec).',
            'identification.digits' => 'La cédula debe contener exactamente 10 dígitos numéricos.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'identification' => 'cédula',
            'name' => 'nombre',
            'email' => 'correo electrónico',
            'password' => 'contraseña',
            'device_name' => 'nombre del dispositivo',
        ];
    }
}

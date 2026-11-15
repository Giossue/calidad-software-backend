<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Http\Requests\Concerns\CombinesPersonNames;
use App\Support\InstitutionalEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class RegisterRequest extends FormRequest
{
    use CombinesPersonNames, PasswordValidationRules, ProfileValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->preparePersonNames();
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
            ...$this->personNameMessages(),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'identification' => 'cédula',
            ...$this->personNameAttributes(),
            'email' => 'correo electrónico',
            'password' => 'contraseña',
            'device_name' => 'nombre del dispositivo',
        ];
    }
}

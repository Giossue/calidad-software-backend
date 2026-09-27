<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class IssueTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'email' => 'correo electrónico',
            'password' => 'contraseña',
            'device_name' => 'nombre del dispositivo',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.required' => 'Ingresa tu correo electrónico.',
            'email.string' => 'El correo electrónico no es válido.',
            'email.email' => 'Ingresa un correo electrónico con un formato válido.',
            'email.max' => 'El correo electrónico no puede superar los 255 caracteres.',
            'password.required' => 'Ingresa tu contraseña.',
            'password.string' => 'La contraseña no es válida.',
            'device_name.required' => 'No fue posible identificar el dispositivo.',
            'device_name.string' => 'No fue posible identificar el dispositivo.',
            'device_name.max' => 'El nombre del dispositivo no puede superar los 100 caracteres.',
        ];
    }
}

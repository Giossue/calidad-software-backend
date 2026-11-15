<?php

namespace App\Concerns;

use App\Rules\PersonNameCount;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'identification' => $this->identificationRules($userId),
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Nombres (uno o dos) y apellidos (dos por regla general; uno solo para quienes
     * su registro civil asigna un único apellido) en campos separados.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function personNameRules(): array
    {
        return [
            'first_names' => ['required', 'string', 'max:70', 'regex:/^[\pL\s]+$/u',
                new PersonNameCount(1, 2, 'Ingresa uno o dos nombres.')],
            'last_names' => ['required', 'string', 'max:70', 'regex:/^[\pL\s]+$/u',
                new PersonNameCount(1, 2, 'Ingresa tus dos apellidos, o uno solo si tu registro civil tiene un único apellido.')],
        ];
    }

    /** @return array<int, ValidationRule|array<mixed>|string> */
    protected function identificationRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'max:20',
            $userId === null
                ? Rule::unique('usuario', 'cedula')
                : Rule::unique('usuario', 'cedula')->ignore($userId, 'id_usuario'),
        ];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique('usuario', 'correo')
                : Rule::unique('usuario', 'correo')->ignore($userId, 'id_usuario'),
        ];
    }
}

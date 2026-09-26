<?php

namespace App\Http\Requests\Api\V1\Coordination;

use Illuminate\Foundation\Http\FormRequest;

class RegisterPeriodSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->hasRole('coordinador_titulacion') || $user->hasRole('administrador'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) ($this->input('name') ?? $this->input('nombre'))),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:50'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nombre del paralelo',
        ];
    }
}

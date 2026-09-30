<?php

namespace App\Http\Requests\Api\V1\Coordination;

use App\Models\TemaTitulacion;
use Illuminate\Foundation\Http\FormRequest;

class RegisterPeriodSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', TemaTitulacion::class) ?? false;
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

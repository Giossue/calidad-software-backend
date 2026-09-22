<?php

namespace App\Http\Requests\Api\V1\Modalities;

use App\Models\Modalidad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreModalityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Modalidad::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['nombre' => trim((string) ($this->input('nombre') ?? $this->input('name')))]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['nombre' => ['bail', 'required', 'string', 'max:100', Rule::unique('modalidad', 'nombre')]];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['nombre' => 'nombre'];
    }
}

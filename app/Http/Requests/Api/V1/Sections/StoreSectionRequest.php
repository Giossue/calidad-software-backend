<?php

namespace App\Http\Requests\Api\V1\Sections;

use App\Models\Paralelo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Paralelo::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['nombre' => trim((string) ($this->input('nombre') ?? $this->input('name')))]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['nombre' => ['bail', 'required', 'string', 'max:50', Rule::unique('paralelo', 'nombre')]];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['nombre' => 'nombre'];
    }
}

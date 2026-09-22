<?php

namespace App\Http\Requests\Api\V1\Modalities;

use App\Models\Modalidad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateModalityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('modality')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['nombre' => trim((string) ($this->input('nombre') ?? $this->input('name')))]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Modalidad $modality */
        $modality = $this->route('modality');

        return ['nombre' => ['bail', 'required', 'string', 'max:100', Rule::unique('modalidad', 'nombre')->ignore($modality)]];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['nombre' => 'nombre'];
    }
}

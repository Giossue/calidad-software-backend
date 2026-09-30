<?php

namespace App\Http\Requests\Api\V1\Coordination;

use App\Models\TemaTitulacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListDegreeTopicsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', TemaTitulacion::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['pendiente', 'aprobado', 'rechazado'])],
            'section_id' => ['nullable', 'integer', 'exists:paralelo,id_paralelo'],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}

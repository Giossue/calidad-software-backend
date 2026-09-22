<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdateFacultyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string|Unique|ValidationRule>> */
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes', 'string', 'max:150',
                Rule::unique('facultad', 'nombre')->ignore($this->route('faculty')),
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'nombre'];
    }
}
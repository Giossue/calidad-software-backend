<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\Carrera;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCareerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Carrera::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'faculty_id' => [
                'required',
                'integer',
                Rule::exists('facultad', 'id_facultad')->where(
                    fn (Builder $query): Builder => $query->where('estado', true),
                ),
            ],
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('carrera', 'nombre'),
            ],
            'cycles_count' => ['nullable', 'integer', 'min:0', 'max:12'],
            'modality_id' => [
                'nullable',
                'integer',
                Rule::exists('modalidad', 'id_modalidad')->where(
                    fn (Builder $query): Builder => $query->where('estado', true),
                ),
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'faculty_id' => 'facultad',
            'name' => 'nombre de la carrera',
            'modality_id' => 'modalidad',
            'cycles_count' => 'cantidad de ciclos',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.unique' => 'Ya existe una carrera con este nombre en el sistema. El nombre de la carrera es único institucionalmente.',
        ];
    }
}

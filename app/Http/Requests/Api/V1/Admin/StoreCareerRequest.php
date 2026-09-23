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
                Rule::unique('carrera', 'nombre')->where(
                    fn (Builder $query): Builder => $query->where('fk_facultad', $this->integer('faculty_id')),
                ),
            ],
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
        ];
    }
}

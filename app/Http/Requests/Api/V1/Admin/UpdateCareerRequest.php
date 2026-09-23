<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\Carrera;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCareerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $career = $this->route('career');

        return $career instanceof Carrera
            && ($this->user()?->can('update', $career) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $routeCareer = $this->route('career');
        $career = $routeCareer instanceof Carrera ? $routeCareer : null;
        $facultyId = $this->has('faculty_id')
            ? $this->integer('faculty_id')
            : $career?->fk_facultad;

        return [
            'faculty_id' => [
                'sometimes',
                'integer',
                Rule::exists('facultad', 'id_facultad')->where(
                    fn (Builder $query): Builder => $query->where('estado', true),
                ),
            ],
            'name' => [
                'sometimes',
                'string',
                'max:150',
                Rule::unique('carrera', 'nombre')
                    ->where(fn (Builder $query): Builder => $query->where('fk_facultad', $facultyId))
                    ->ignore($career?->getKey(), $career?->getKeyName()),
            ],
            'modality_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('modalidad', 'id_modalidad')->where(
                    fn (Builder $query): Builder => $query->where('estado', true),
                ),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->hasAny(['faculty_id', 'name', 'modality_id'])) {
                $validator->errors()->add('career', 'Debes enviar al menos un campo para actualizar.');
            }
        });
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

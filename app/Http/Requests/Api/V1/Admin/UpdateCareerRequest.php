<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Actions\Academic\GenerateCareerCycles;
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

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => mb_strtoupper(trim($this->input('code')))]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $routeCareer = $this->route('career');
        $career = $routeCareer instanceof Carrera ? $routeCareer : null;

        $currentCycles = (int) ($career?->ciclos()->max('numero') ?? 0);

        return [
            'cycles_count' => ['sometimes', 'integer', 'min:'.$currentCycles, 'max:'.GenerateCareerCycles::MAX_CYCLES],
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
                // Otra malla de la misma carrera se registra con el mismo nombre y otro código.
                Rule::unique('carrera', 'nombre')
                    ->where('codigo', $this->has('code') ? $this->input('code') : $career?->codigo)
                    ->ignore($career?->getKey(), $career?->getKeyName()),
            ],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:30',
                Rule::unique('carrera', 'codigo')
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
            if (! $this->hasAny(['faculty_id', 'name', 'code', 'modality_id', 'cycles_count'])) {
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
            'code' => 'código de carrera',
            'modality_id' => 'modalidad',
            'cycles_count' => 'cantidad de ciclos',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cycles_count.min' => 'No se pueden quitar ciclos desde aquí: la carrera ya tiene :min. Si no usas alguno, desactívalo.',
            'name.unique' => 'Ya existe una carrera con este nombre y este código. Para registrar otra malla de la carrera, usa un código distinto.',
            'code.unique' => 'Ya existe una carrera con este código. El código de carrera es único institucionalmente.',
        ];
    }
}

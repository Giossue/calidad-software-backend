<?php

namespace App\Http\Requests\Api\V1\AcademicPeriods;

use App\Models\PeriodoAcademico;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAcademicPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('academicPeriod')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['nombre' => trim((string) $this->input('nombre'))]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var PeriodoAcademico $academicPeriod */
        $academicPeriod = $this->route('academicPeriod');

        return [
            'nombre' => ['bail', 'required', 'string', 'max:100', Rule::unique('periodo_academico', 'nombre')->ignore($academicPeriod)],
            'fecha_inicio' => ['bail', 'required', 'date_format:Y-m-d'],
            'fecha_fin' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['nombre' => 'nombre', 'fecha_inicio' => 'fecha de inicio', 'fecha_fin' => 'fecha de fin'];
    }
}

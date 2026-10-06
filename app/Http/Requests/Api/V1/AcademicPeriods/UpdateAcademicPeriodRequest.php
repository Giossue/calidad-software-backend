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
        $this->merge([
            'nombre' => trim((string) ($this->input('nombre') ?? $this->input('name'))),
            'fecha_inicio' => $this->input('fecha_inicio') ?? $this->input('start_date'),
            'fecha_fin' => $this->input('fecha_fin') ?? $this->input('end_date'),
        ]);
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

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            /** @var PeriodoAcademico $academicPeriod */
            $academicPeriod = $this->route('academicPeriod');
            $inicio = $this->input('fecha_inicio');
            $fin = $this->input('fecha_fin');

            if ($inicio && $fin && $inicio <= $fin && $academicPeriod) {
                $overlapping = PeriodoAcademico::query()
                    ->where('id_periodo', '!=', $academicPeriod->getKey())
                    ->where(function ($query) use ($inicio, $fin) {
                        $query->where('fecha_inicio', '<=', $fin)
                            ->where('fecha_fin', '>=', $inicio);
                    })
                    ->first();

                if ($overlapping) {
                    $validator->errors()->add(
                        'fecha_inicio',
                        "El rango de fechas coincide con el período '{$overlapping->nombre}' ({$overlapping->fecha_inicio->format('Y-m-d')} al {$overlapping->fecha_fin->format('Y-m-d')}). Solo debe existir un PAO a la vez.",
                    );
                }
            }
        });
    }
}

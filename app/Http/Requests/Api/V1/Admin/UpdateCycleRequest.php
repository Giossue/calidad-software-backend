<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\Ciclo;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cycle = $this->route('cycle');

        return $cycle instanceof Ciclo
            && ($this->user()?->can('update', $cycle) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $routeCycle = $this->route('cycle');
        $cycle = $routeCycle instanceof Ciclo ? $routeCycle : null;
        $careerId = $this->has('career_id')
            ? $this->integer('career_id')
            : $cycle?->fk_carrera;
        $name = $this->has('name')
            ? $this->input('name')
            : $cycle?->nombre;
        $paraleloId = $this->has('paralelo_id')
            ? ($this->integer('paralelo_id') ?: null)
            : $cycle?->fk_paralelo;

        return [
            'career_id' => [
                'sometimes',
                'integer',
                Rule::exists('carrera', 'id_carrera')->where(
                    fn (Builder $query): Builder => $query->where('estado', true),
                ),
            ],
            'name' => ['sometimes', 'string', 'max:100'],
            'number' => [
                'sometimes',
                'integer',
                'min:1',
                Rule::unique('ciclo', 'numero')
                    ->where(fn (Builder $query): Builder => $query
                        ->where('fk_carrera', $careerId)
                        ->where('fk_paralelo', $paraleloId))
                    ->ignore($cycle?->getKey(), $cycle?->getKeyName()),
            ],
            'paralelo_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('paralelo', 'id_paralelo')->where(
                    fn (Builder $query): Builder => $query->where('estado', true),
                ),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->hasAny(['career_id', 'name', 'number', 'paralelo_id'])) {
                $validator->errors()->add('cycle', 'Debes enviar al menos un campo para actualizar.');
            }
        });
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'career_id' => 'carrera',
            'name' => 'nombre del ciclo',
            'number' => 'número del ciclo',
            'paralelo_id' => 'paralelo',
        ];
    }
}

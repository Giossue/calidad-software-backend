<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\Ciclo;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Ciclo::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'career_id' => [
                'required',
                'integer',
                Rule::exists('carrera', 'id_carrera')->where(
                    fn (Builder $query): Builder => $query->where('estado', true),
                ),
            ],
            'name' => ['required', 'string', 'max:100'],
            'number' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('ciclo', 'numero')->where(
                    fn (Builder $query): Builder => $query->where('fk_carrera', $this->integer('career_id')),
                ),
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'career_id' => 'carrera',
            'name' => 'nombre del ciclo',
            'number' => 'número del ciclo',
        ];
    }
}

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

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => mb_strtoupper(trim($this->input('code')))]);
        }
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
                // Otra malla de la misma carrera se registra con el mismo nombre y otro código.
                Rule::unique('carrera', 'nombre')->where('codigo', $this->input('code')),
            ],
            'code' => [
                $this->codeIsRequired() ? 'required' : 'nullable',
                'string',
                'max:30',
                Rule::unique('carrera', 'codigo'),
            ],
            'cycles_count' => ['nullable', 'integer', 'min:0', 'max:12'],
            'modality_id' => [
                'required',
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
            'code' => 'código de carrera',
            'modality_id' => 'modalidad',
            'cycles_count' => 'cantidad de ciclos',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.unique' => 'Ya existe una carrera con este nombre y este código. Para registrar otra malla de la carrera, usa un código distinto.',
            'code.unique' => 'Ya existe una carrera con este código. El código de carrera es único institucionalmente.',
        ];
    }

    /**
     * El administrador registra el código al crear la carrera; la importación
     * masiva lo acepta como columna opcional.
     */
    protected function codeIsRequired(): bool
    {
        return true;
    }
}

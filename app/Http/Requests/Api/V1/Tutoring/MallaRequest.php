<?php

namespace App\Http\Requests\Api\V1\Tutoring;

use App\Http\Requests\Concerns\UppercasesInput;
use App\Models\Malla;
use App\Models\Subject;
use App\Support\TutoringCoordinatorAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Crear o editar una malla. El nombre es único en la carrera. Una malla se marca
 * vigente con `is_current: true`; no se desmarca: se marca otra en su lugar.
 */
class MallaRequest extends FormRequest
{
    use UppercasesInput;

    protected function uppercaseFields(): array
    {
        return ['name'];
    }

    public function authorize(): bool
    {
        return $this->user()->can('create', Subject::class);
    }

    /** El coordinador debe serlo de la carrera de la malla (403 si no). */
    protected function passedValidation(): void
    {
        app(TutoringCoordinatorAccess::class)->authorizeCareer($this->user(), $this->careerId());
    }

    private function careerId(): int
    {
        $malla = $this->route('malla');

        return $malla instanceof Malla ? $malla->career_id : $this->integer('career_id');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $malla = $this->route('malla');
        $creating = ! $malla instanceof Malla;

        return [
            'career_id' => $creating ? ['required', 'integer', Rule::exists('carrera', 'id_carrera')] : ['prohibited'],
            'name' => [$creating ? 'required' : 'sometimes', 'required', 'string', 'max:100',
                Rule::unique('malla', 'name')->where('career_id', $this->careerId())->ignore($creating ? null : $malla->getKey())],
            'is_current' => $creating ? ['sometimes', 'boolean'] : ['sometimes', 'accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.unique' => 'Ya existe una malla con ese nombre en la carrera.',
            'is_current.accepted' => 'Una carrera siempre tiene una malla vigente: marca como vigente otra malla.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['career_id' => 'carrera', 'name' => 'nombre', 'is_current' => 'vigente'];
    }
}

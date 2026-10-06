<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Models\Usuario;
use Illuminate\Validation\Validator;

/**
 * El ciclo del estudiante debe existir en su carrera: el último ciclo es
 * titulación y los anteriores, tutorías.
 */
trait ValidatesStudentCycle
{
    /** @return array<string, array<int, string>> */
    protected function studentCycleRules(): array
    {
        return ['cycle_number' => ['nullable', 'integer', 'min:1', 'required_if:role,estudiante']];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->filled('cycle_number')) {
                return;
            }

            $current = $this->route('user');
            $careerId = $this->exists('career_id')
                ? $this->integer('career_id')
                : ($current instanceof Usuario ? $current->fk_carrera : null);

            if (! $careerId) {
                $validator->errors()->add('career_id', 'Selecciona la carrera del estudiante para registrar su ciclo.');

                return;
            }

            $cycles = Usuario::careerCycleCount($careerId);
            if ($this->integer('cycle_number') > $cycles) {
                $validator->errors()->add('cycle_number', "La carrera tiene {$cycles} ciclos; el último corresponde a titulación.");
            }
        });
    }
}

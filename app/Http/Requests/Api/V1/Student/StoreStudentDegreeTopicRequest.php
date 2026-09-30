<?php

namespace App\Http\Requests\Api\V1\Student;

use App\Models\PeriodoAcademico;
use App\Models\TemaTitulacion;
use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreStudentDegreeTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Usuario|null $user */
        $user = $this->user();

        return $user !== null && ($user->hasRole('estudiante') || $user->hasRole('administrador'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['bail', 'required_without:titulo', 'nullable', 'string', 'min:5', 'max:255'],
            'titulo' => ['bail', 'required_without:title', 'nullable', 'string', 'min:5', 'max:255'],
            'description' => ['bail', 'nullable', 'string', 'max:2000'],
            'descripcion' => ['bail', 'nullable', 'string', 'max:2000'],
            'academic_period_id' => ['bail', 'nullable', 'integer', 'exists:periodo_academico,id_periodo'],
            'section_id' => ['bail', 'nullable', 'integer', 'exists:paralelo,id_paralelo'],
            'replace_pending' => ['bail', 'nullable', 'boolean'],
            'reemplazar_pendiente' => ['bail', 'nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'title' => 'título del tema',
            'titulo' => 'título del tema',
            'description' => 'descripción',
            'descripcion' => 'descripción',
            'academic_period_id' => 'período académico',
            'section_id' => 'paralelo',
            'replace_pending' => 'reemplazar propuesta pendiente',
            'reemplazar_pendiente' => 'reemplazar propuesta pendiente',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Usuario|null $user */
            $user = $this->user();

            if (! $user) {
                return;
            }

            $periodId = $this->filled('academic_period_id')
                ? $this->integer('academic_period_id')
                : PeriodoAcademico::query()->where('estado', true)->value('id_periodo');

            if (! $periodId) {
                $validator->errors()->add(
                    'academic_period_id',
                    'No existe un período académico vigente para registrar la propuesta de titulación.',
                );

                return;
            }

            // Validar que el estudiante no tenga ya un tema aprobado en este período
            $hasApproved = TemaTitulacion::query()
                ->where('fk_id_usuario', $user->getKey())
                ->where('fk_periodo', $periodId)
                ->where('estado', 'aprobado')
                ->exists();

            if ($hasApproved) {
                $validator->errors()->add(
                    'title',
                    'Ya cuentas con un tema de titulación aprobado en este período académico.',
                );
            }

            // Validar que el estudiante no tenga ya una propuesta pendiente de revisión (a menos que indique reemplazarla)
            $hasPending = TemaTitulacion::query()
                ->where('fk_id_usuario', $user->getKey())
                ->where('fk_periodo', $periodId)
                ->where('estado', 'pendiente')
                ->exists();

            $wantsToReplace = $this->boolean('replace_pending') || $this->boolean('reemplazar_pendiente');

            if ($hasPending && ! $wantsToReplace) {
                $validator->errors()->add(
                    'title',
                    'Ya cuentas con una propuesta de tema de titulación pendiente de revisión en este período académico. Puedes cambiarla directamente o enviar "replace_pending": true para registrar una alternativa.',
                );
            }
        });
    }

    public function wantsToReplacePending(): bool
    {
        return $this->boolean('replace_pending') || $this->boolean('reemplazar_pendiente');
    }

    public function resolvedTitle(): string
    {
        return (string) ($this->input('title') ?? $this->input('titulo'));
    }

    public function resolvedDescription(): ?string
    {
        $desc = $this->input('description') ?? $this->input('descripcion');

        return $desc !== null ? (string) $desc : null;
    }

    public function resolvedPeriodId(): int
    {
        if ($this->filled('academic_period_id')) {
            return $this->integer('academic_period_id');
        }

        return (int) PeriodoAcademico::query()->where('estado', true)->value('id_periodo');
    }
}

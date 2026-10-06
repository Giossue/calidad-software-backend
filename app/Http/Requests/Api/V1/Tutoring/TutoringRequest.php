<?php

namespace App\Http\Requests\Api\V1\Tutoring;

use App\Models\AsignaturaTutoria;
use App\Models\Ciclo;
use App\Models\PeriodoAcademico;
use App\Models\Subject;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TutoringRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tutoring = $this->route('tutoring');

        return $tutoring instanceof AsignaturaTutoria
            ? $this->user()->can('update', $tutoring)
            : $this->user()->can('create', AsignaturaTutoria::class);
    }

    protected function prepareForValidation(): void
    {
        if ($this->isMethod('post')) {
            $cycleId = $this->integer('cycle_id');
            $subjectId = $this->integer('subject_id');

            if (! $this->filled('period_id') && $cycleId) {
                $cycle = Ciclo::with('periodos')->find($cycleId);
                $periodId = $cycle?->periodos()->where('periodo_academico.estado', true)->latest('id_periodo')->value('periodo_academico.id_periodo')
                    ?? PeriodoAcademico::query()->where('estado', true)->latest('id_periodo')->value('id_periodo');
                if ($periodId) {
                    $this->merge(['period_id' => $periodId]);
                }
            }

            if (! $this->filled('modality_id') && ($subjectId || $cycleId)) {
                $subject = $subjectId ? Subject::with('career')->find($subjectId) : null;
                $cycle = $cycleId ? Ciclo::with('carrera')->find($cycleId) : null;
                $modalityId = $subject->modality_id
                    ?? $cycle->carrera->fk_modalidad
                    ?? $subject?->career?->fk_modalidad;
                if ($modalityId) {
                    $this->merge(['modality_id' => $modalityId]);
                }
            }
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $creating = $this->isMethod('post');
        $current = $this->route('tutoring');
        $periodId = $current instanceof AsignaturaTutoria ? $current->fk_periodo : null;
        $modalityId = $current instanceof AsignaturaTutoria ? $current->fk_modalidad : null;

        return [
            'subject_id' => $creating ? ['required', 'integer', Rule::exists('subjects', 'id')->where('is_active', true)] : ['prohibited'],
            'cycle_id' => $creating ? ['required', 'integer', Rule::exists('ciclo', 'id_ciclo')->where('estado', true)] : ['prohibited'],
            'parallel_ids' => ['nullable', 'array'],
            'parallel_ids.*' => ['integer', Rule::exists('paralelo', 'id_paralelo')->where('estado', true)],
            'parallel_id' => ['nullable', 'integer', Rule::exists('paralelo', 'id_paralelo')->where('estado', true)],
            'period_id' => [($creating ? 'required' : 'sometimes'), 'integer', Rule::exists('periodo_academico', 'id_periodo')->where(
                fn (Builder $query) => $query->where('estado', true)->when($periodId, fn (Builder $q) => $q->orWhere('id_periodo', $periodId)),
            )],
            'modality_id' => [($creating ? 'required' : 'sometimes'), 'integer', Rule::exists('modalidad', 'id_modalidad')->where(
                fn (Builder $query) => $query->where('estado', true)->when($modalityId, fn (Builder $q) => $q->orWhere('id_modalidad', $modalityId)),
            )],
            'teacher_id' => ['nullable', 'integer', Rule::exists('usuario', 'id_usuario')->where('estado', true)],
            'schedules' => ['nullable', 'array'],
            'schedules.*.day' => ['required', 'string', Rule::in(['lunes', 'martes', 'miercoles', 'miércoles', 'jueves', 'viernes', 'sabado', 'sábado', 'domingo'])],
            'schedules.*.start_time' => ['required', 'string'],
            'schedules.*.end_time' => ['required', 'string'],
            'schedules.*.room' => ['nullable', 'string', 'max:100'],
        ];
    }
}

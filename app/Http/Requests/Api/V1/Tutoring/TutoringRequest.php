<?php

namespace App\Http\Requests\Api\V1\Tutoring;

use App\Models\AsignaturaTutoria;
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
            'period_id' => [($creating ? 'required' : 'sometimes'), 'integer', Rule::exists('periodo_academico', 'id_periodo')->where(
                fn (Builder $query) => $query->where('estado', true)->when($periodId, fn (Builder $q) => $q->orWhere('id_periodo', $periodId)),
            )],
            'modality_id' => [($creating ? 'required' : 'sometimes'), 'integer', Rule::exists('modalidad', 'id_modalidad')->where(
                fn (Builder $query) => $query->where('estado', true)->when($modalityId, fn (Builder $q) => $q->orWhere('id_modalidad', $modalityId)),
            )],
        ];
    }
}

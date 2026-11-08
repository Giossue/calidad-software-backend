<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Subject */
class TutoringSubjectResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $linkedPeriod = $this->relationLoaded('cycles')
            ? $this->cycles->flatMap->periodos->first()
            : null;

        return [
            'id' => $this->getKey(),
            'career_id' => $this->career_id,
            'career_name' => $this->whenLoaded('career', fn () => $this->career->nombre),
            'code' => $this->code,
            'name' => $this->name,
            'modality_id' => $this->modality_id,
            'modality_name' => $this->relationLoaded('modality') ? $this->modality?->nombre : null,
            'period_id' => $linkedPeriod?->id_periodo,
            'period_name' => $linkedPeriod?->nombre,
            'is_active' => $this->is_active,
            'cycle_ids' => $this->whenLoaded('cycles', fn () => $this->cycles->pluck('id_ciclo')->all()),
            'parallel_ids' => $this->whenLoaded('cycles', fn () => $this->cycles->pluck('fk_paralelo')->filter()->unique()->values()->all()),
            // Secuencia de la malla: a qué asignaturas continúa y cuáles hay que aprobar antes.
            'next_subjects' => $this->whenLoaded('nextSubjects', fn () => $this->nextSubjects->map(fn ($next) => ['id' => $next->id, 'code' => $next->code, 'name' => $next->name])->values()->all()),
            'previous_subjects' => $this->whenLoaded('previousSubjects', fn () => $this->previousSubjects->map(fn ($previous) => ['id' => $previous->id, 'code' => $previous->code, 'name' => $previous->name])->values()->all()),
        ];
    }
}

<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AsignaturaTutoria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AsignaturaTutoria */
class TutoringResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'subject_id' => $this->subject_id,
            'subject_name' => $this->nombre,
            'cycle_id' => $this->fk_ciclo,
            'cycle_name' => $this->whenLoaded('ciclo', fn () => $this->ciclo?->nombre),
            'career_id' => $this->whenLoaded('ciclo', fn () => $this->ciclo?->fk_carrera),
            'period_id' => $this->fk_periodo,
            'period_name' => $this->whenLoaded('periodo', fn () => $this->periodo?->nombre),
            'modality_id' => $this->fk_modalidad,
            'modality_name' => $this->whenLoaded('modalidad', fn () => $this->modalidad?->nombre),
            'section_id' => $this->fk_paralelo,
            'section_name' => $this->whenLoaded('paralelo', fn () => $this->paralelo?->nombre),
            'teacher_id' => $this->fk_docente,
            'teacher_name' => $this->whenLoaded('docente', fn () => $this->docente?->nombre),
            'teacher_is_active' => $this->whenLoaded('docente', fn () => $this->docente?->estado),
            'subject_is_active' => $this->whenLoaded('subject', fn () => $this->subject?->is_active),
            'is_active' => $this->estado,
            'schedules' => TutoringScheduleResource::collection($this->whenLoaded('horarios', fn () => $this->horarios->where('estado', true))),
        ];
    }
}

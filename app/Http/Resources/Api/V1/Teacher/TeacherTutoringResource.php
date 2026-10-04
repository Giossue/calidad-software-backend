<?php

namespace App\Http\Resources\Api\V1\Teacher;

use App\Http\Resources\Api\V1\TutoringResource;
use App\Http\Resources\Api\V1\TutoringScheduleResource;
use App\Models\AsignaturaTutoria;
use Illuminate\Http\Request;

/** @mixin AsignaturaTutoria */
class TeacherTutoringResource extends TutoringResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [...parent::toArray($request),
            'career_name' => $this->ciclo->carrera->nombre ?? '',
            'period_start_date' => $this->periodo?->fecha_inicio?->toDateString() ?? '',
            'period_end_date' => $this->periodo?->fecha_fin?->toDateString() ?? '',
            'can_manage' => (bool) $request->user()?->can('teach', $this->resource),
            'active_enrollment_count' => $this->active_enrollment_count,
            'schedules' => TutoringScheduleResource::collection($this->whenLoaded('horarios')),
        ];
    }
}

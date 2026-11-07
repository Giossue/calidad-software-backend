<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AsignaturaTutoria;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Usuario */
class TutoringTeacherResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'identification' => $this->cedula,
            'name' => $this->nombre,
            'email' => $this->correo,
            'phone' => $this->telefono,
            'is_active' => $this->estado,
            'can_manage' => $request->user()->can('updateTutoringTeacher', $this->resource),
            'career_ids' => $this->whenLoaded('teachingCareers', fn () => $this->teachingCareers->pluck('id_carrera')->all()),
            'tutorings' => $this->whenLoaded('asignaturasTutoria', fn () => $this->asignaturasTutoria->map(fn (AsignaturaTutoria $tutoring) => [
                'id' => $tutoring->getKey(),
                'subject_name' => $tutoring->nombre,
                'cycle_name' => $tutoring->ciclo?->nombre,
                'cycle_number' => $tutoring->ciclo?->numero,
                'section_name' => $tutoring->paralelo?->nombre,
            ])->values()->all()),
        ];
    }
}

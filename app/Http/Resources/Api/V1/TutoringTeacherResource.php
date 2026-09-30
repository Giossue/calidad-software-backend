<?php

namespace App\Http\Resources\Api\V1;

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
        ];
    }
}

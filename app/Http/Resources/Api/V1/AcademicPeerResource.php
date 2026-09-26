<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AsignacionDocente;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AsignacionDocente */
class AcademicPeerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $teacher = $this->docente;

        return [
            'assignment_id' => $this->getKey(),
            'teacher_id' => $teacher?->getKey(),
            'identification' => $teacher?->cedula,
            'name' => $teacher?->nombre,
            'email' => $teacher?->correo,
            'phone' => $teacher?->telefono,
            'role' => $this->rol,
            'assigned_at' => $this->fecha_asignacion?->toDateString(),
            'is_active' => $this->estado,
        ];
    }
}

<?php

namespace App\Http\Resources\Api\V1\Teacher;

use App\Models\AsignacionDocente;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AsignacionDocente */
class DegreeAssignmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $topic = $this->temaTitulacion;

        return [
            'id' => $this->getKey(), 'role' => $this->rol, 'assigned_at' => $this->fecha_asignacion?->toDateString(),
            'topic' => ['id' => $topic->getKey(), 'title' => $topic->titulo, 'description' => $topic->descripcion, 'status' => $topic->estado],
            'student' => ['id' => $topic->estudiante->getKey(), 'name' => $topic->estudiante->nombre, 'email' => $topic->estudiante->correo],
            'period' => ['id' => $topic->periodo->getKey(), 'name' => $topic->periodo->nombre],
        ];
    }
}

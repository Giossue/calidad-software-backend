<?php

namespace App\Http\Resources\Api\V1;

use App\Models\TemaTitulacion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TemaTitulacion */
class StudentDegreeAssignmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $student = $this->estudiante;
        $section = $student?->paralelos?->first();
        $period = $this->periodo;
        $coordinator = $this->coordinadorRevisor;

        $tutorAssignment = $this->asignaciones->firstWhere('rol', 'tutor');
        $tutor = $tutorAssignment?->docente;

        $peerAssignments = $this->asignaciones->where('rol', 'par_academico');

        return [
            'topic' => [
                'id' => $this->getKey(),
                'title' => $this->titulo,
                'description' => $this->descripcion,
                'status' => $this->estado,
                'proposed_at' => $this->fecha_propuesta?->toDateString(),
                'approved_at' => $this->fecha_revision?->toDateString(),
                'academic_period' => $period ? [
                    'id' => $period->getKey(),
                    'name' => $period->nombre,
                    'is_active' => $period->estado,
                ] : null,
                'section' => $section ? [
                    'id' => $section->getKey(),
                    'name' => $section->nombre,
                ] : null,
                'coordinator' => $coordinator ? [
                    'id' => $coordinator->getKey(),
                    'name' => $coordinator->nombre,
                    'email' => $coordinator->correo,
                ] : null,
            ],
            'tutor' => $tutor ? [
                'assignment_id' => $tutorAssignment?->getKey(),
                'id' => $tutor->getKey(),
                'name' => $tutor->nombre,
                'email' => $tutor->correo,
                'phone' => $tutor->telefono,
                'role' => 'tutor',
                'assigned_at' => $tutorAssignment?->fecha_asignacion?->toDateString(),
            ] : null,
            'peers' => $peerAssignments->map(fn ($assignment) => [
                'assignment_id' => $assignment->getKey(),
                'id' => $assignment->docente?->getKey(),
                'name' => $assignment->docente?->nombre,
                'email' => $assignment->docente?->correo,
                'phone' => $assignment->docente?->telefono,
                'role' => $assignment->rol,
                'assigned_at' => $assignment->fecha_asignacion?->toDateString(),
            ])->values(),
            'total_teachers_assigned' => $this->asignaciones->count(),
        ];
    }
}

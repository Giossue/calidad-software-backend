<?php

namespace App\Http\Resources\Api\V1;

use App\Models\TemaTitulacion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TemaTitulacion */
class DegreeTopicResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $student = $this->estudiante;
        $section = $student?->paralelos?->first();
        $period = $this->periodo;
        $reviewer = $this->coordinadorRevisor;

        return [
            'id' => $this->getKey(),
            'title' => $this->titulo,
            'description' => $this->descripcion,
            'status' => $this->estado,
            'proposed_at' => $this->fecha_propuesta?->toDateString(),
            'reviewed_at' => $this->fecha_revision?->toDateString(),
            'student' => $student ? [
                'id' => $student->getKey(),
                'identification' => $student->cedula,
                'name' => $student->nombre,
                'email' => $student->correo,
                'phone' => $student->telefono,
            ] : null,
            'section' => $section ? [
                'id' => $section->getKey(),
                'name' => $section->nombre,
            ] : null,
            'academic_period' => $period ? [
                'id' => $period->getKey(),
                'name' => $period->nombre,
                'is_active' => $period->estado,
            ] : null,
            'reviewer' => $reviewer ? [
                'id' => $reviewer->getKey(),
                'name' => $reviewer->nombre,
                'email' => $reviewer->correo,
            ] : null,
            'assignments' => $this->activeAssignments->map(fn ($assignment) => [
                'id' => $assignment->getKey(),
                'role' => $assignment->rol,
                'assigned_at' => $assignment->fecha_asignacion?->toDateString(),
                'teacher' => $assignment->docente ? [
                    'id' => $assignment->docente->getKey(),
                    'name' => $assignment->docente->nombre,
                    'email' => $assignment->docente->correo,
                ] : null,
            ]),
            'observations' => $this->observaciones->map(fn ($obs) => [
                'id' => $obs->getKey(),
                'observation' => $obs->descripcion,
                'registered_at' => $obs->fecha_registro?->toDateString(),
                'coordinator' => $obs->coordinador ? [
                    'id' => $obs->coordinador->getKey(),
                    'name' => $obs->coordinador->nombre,
                    'email' => $obs->coordinador->correo,
                ] : null,
            ]),
            'tracking' => $this->fichaSeguimiento ? [
                'id' => $this->fichaSeguimiento->getKey(),
                'opened_at' => $this->fichaSeguimiento->fecha_apertura?->toDateString(),
                'progress_percentage' => (float) $this->fichaSeguimiento->porcentaje_avance,
                'status' => $this->fichaSeguimiento->estado,
                'activities' => $this->fichaSeguimiento->actividades->map(fn ($act) => [
                    'id' => $act->getKey(),
                    'description' => $act->descripcion,
                    'is_completed' => (bool) $act->completada,
                    'registered_at' => $act->fecha_registro?->toDateString(),
                    'teacher' => $act->docente ? [
                        'id' => $act->docente->getKey(),
                        'name' => $act->docente->nombre,
                        'email' => $act->docente->correo,
                        'role' => ($this->activeAssignments->firstWhere('fk_id_usuario', $act->docente->getKey())
                            ?? $this->asignaciones->firstWhere('fk_id_usuario', $act->docente->getKey()))?->rol,
                    ] : null,
                ]),
            ] : null,
        ];
    }
}

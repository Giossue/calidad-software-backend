<?php

namespace App\Http\Resources\Api\V1;

use App\Models\InscripcionTutoria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InscripcionTutoria */
class StudentTutoringResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $subject = $this->asignaturaTutoria;
        $period = $subject?->periodo;
        $section = $subject?->paralelo;
        $modality = $subject?->modalidad;
        $cycle = $subject?->ciclo;
        $teacher = $subject?->docente;
        $schedules = $subject?->horarios ?? collect();

        return [
            'id' => $this->getKey(),
            'enrolled_at' => $this->fecha_inscripcion?->toDateString(),
            'is_active' => $this->estado,
            'subject' => $subject ? [
                'id' => $subject->getKey(),
                'name' => $subject->nombre,
                'is_active' => $subject->estado,
                'academic_period' => $period ? [
                    'id' => $period->getKey(),
                    'name' => $period->nombre,
                    'is_active' => $period->estado,
                ] : null,
                'section' => $section ? [
                    'id' => $section->getKey(),
                    'name' => $section->nombre,
                ] : null,
                'modality' => $modality ? [
                    'id' => $modality->getKey(),
                    'name' => $modality->nombre,
                ] : null,
                'cycle' => $cycle ? [
                    'id' => $cycle->getKey(),
                    'name' => $cycle->nombre,
                    'number' => $cycle->numero,
                ] : null,
                'teacher' => $teacher ? [
                    'id' => $teacher->getKey(),
                    'name' => $teacher->nombre,
                    'email' => $teacher->correo,
                    'phone' => $teacher->telefono,
                ] : null,
                'schedules' => $schedules->map(fn ($schedule) => [
                    'id' => $schedule->getKey(),
                    'day_of_week' => $schedule->dia_semana,
                    'start_time' => $schedule->hora_inicio,
                    'end_time' => $schedule->hora_fin,
                    'is_active' => $schedule->estado,
                ]),
            ] : null,
        ];
    }
}

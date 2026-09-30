<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Asistencia;
use App\Models\InscripcionTutoria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InscripcionTutoria */
class StudentTutoringAttendanceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $subject = $this->asignaturaTutoria;
        $period = $subject?->periodo;
        $section = $subject?->paralelo;
        $cycle = $subject?->ciclo;
        $teacher = $subject?->docente;

        $attendances = ($this->relationLoaded('asistencias') ? $this->asistencias : $this->asistencias()->with('session.topics')->get())
            ->sortByDesc('fecha');

        $totalSessions = $attendances->count();
        $presentCount = $attendances->where('estado_asistencia', true)->count();
        $absentCount = $attendances->where('estado_asistencia', false)->count();
        $percentage = $totalSessions > 0
            ? round(($presentCount / $totalSessions) * 100, 2)
            : 0.0;

        return [
            'enrollment_id' => $this->getKey(),
            'enrolled_at' => $this->fecha_inscripcion?->toDateString(),
            'is_active' => $this->estado,
            'tutoring' => $subject ? [
                'id' => $subject->getKey(),
                'name' => $subject->nombre,
                'is_active' => $subject->estado,
                'academic_period' => $period ? [
                    'id' => $period->getKey(),
                    'name' => $period->nombre,
                ] : null,
                'section' => $section ? [
                    'id' => $section->getKey(),
                    'name' => $section->nombre,
                ] : null,
                'cycle' => $cycle ? [
                    'id' => $cycle->getKey(),
                    'name' => $cycle->nombre,
                ] : null,
                'teacher' => $teacher ? [
                    'id' => $teacher->getKey(),
                    'name' => $teacher->nombre,
                    'email' => $teacher->correo,
                ] : null,
            ] : null,
            'summary' => [
                'total_sessions' => $totalSessions,
                'present_count' => $presentCount,
                'absent_count' => $absentCount,
                'attendance_percentage' => $percentage,
            ],
            'records' => $attendances->values()->map(function (Asistencia $asistencia) {
                $session = $asistencia->relationLoaded('session') ? $asistencia->session : null;
                $topics = $session && $session->relationLoaded('topics')
                    ? $session->topics->map(fn ($topic) => [
                        'id' => $topic->getKey(),
                        'name' => $topic->nombre,
                    ])
                    : collect();

                return [
                    'id' => $asistencia->getKey(),
                    'date' => $asistencia->fecha?->toDateString(),
                    'present' => (bool) $asistencia->estado_asistencia,
                    'status' => $asistencia->estado_asistencia ? 'presente' : 'ausente',
                    'topics_covered' => $session ? (bool) $session->topics_covered : null,
                    'topics' => $topics,
                ];
            }),
        ];
    }
}

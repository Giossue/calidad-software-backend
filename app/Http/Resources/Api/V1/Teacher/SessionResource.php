<?php

namespace App\Http\Resources\Api\V1\Teacher;

use App\Models\Actividad;
use App\Models\Tema;
use App\Models\TutoringSession;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TutoringSession */
class SessionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $sessionDate = $this->date instanceof CarbonInterface ? $this->date->toDateString() : (string) $this->date;
        $topicIds = $this->topics->pluck('id_tema')->all();

        $activities = Actividad::query()
            ->whereHas('tema', fn ($q) => $q->where('fk_asig_tutoria', $this->tutoring_id))
            ->where(function ($q) use ($sessionDate, $topicIds) {
                $q->whereDate('created_at', $sessionDate);
                if (! empty($topicIds)) {
                    $q->orWhereIn('fk_tema', $topicIds);
                }
            })
            ->with('tema', 'metodologias')
            ->orderBy('id_actividad')
            ->get();

        $topics = $this->topics;
        if ($topics->isEmpty() && $this->topics_covered) {
            $topics = Tema::query()
                ->where('fk_asig_tutoria', $this->tutoring_id)
                ->where('estado', true)
                ->where(function ($q) use ($sessionDate) {
                    $q->whereDate('created_at', $sessionDate)
                        ->orWhereDate('updated_at', $sessionDate)
                        ->orWhere('visto', true);
                })
                ->with(['actividades.metodologias'])
                ->get();
        }

        if ($activities->isEmpty() && $topics->isNotEmpty()) {
            $activities = $topics->flatMap->actividades->where('estado', true)->values();
        }

        return [
            'id' => $this->getKey(),
            'tutoring_id' => $this->tutoring_id,
            'date' => $this->date->toDateString(),
            'topics_covered' => $this->topics_covered,
            'topics' => $topics->map(fn (Tema $topic) => [
                'id' => $topic->getKey(),
                'name' => $topic->nombre,
                'description' => $topic->descripcion,
                'is_active' => (bool) $topic->estado,
                'is_covered' => (bool) $topic->visto,
                'activities' => $topic->actividades ? $topic->actividades->where('estado', true)->values()->map(fn (Actividad $act) => [
                    'id' => $act->id_actividad,
                    'topic_id' => $act->fk_tema,
                    'topic_name' => $topic->nombre,
                    'name' => $act->nombre,
                    'duration' => $act->duracion,
                    'methodologies' => $act->metodologias ? $act->metodologias->where('estado', true)->pluck('descripcion')->values() : [],
                ]) : [],
            ]),
            'activities' => $activities->map(fn ($act) => [
                'id' => $act->id_actividad,
                'topic_id' => $act->fk_tema,
                'topic_name' => $act->tema->nombre ?? '',
                'name' => $act->nombre,
                'duration' => $act->duracion,
                'methodologies' => $act->metodologias ? $act->metodologias->where('estado', true)->pluck('descripcion')->values() : [],
            ]),
            'attendance' => $this->attendance->map(fn ($record) => [
                'id' => $record->getKey(),
                'enrollment_id' => $record->fk_inscripcion,
                'student_id' => $record->fk_id_usuario,
                'student_name' => $record->estudiante->nombre ?? '',
                'student_identification' => $record->estudiante->cedula ?? '',
                'student_email' => $record->estudiante->correo ?? '',
                'present' => (bool) $record->estado_asistencia,
            ]),
        ];
    }
}

<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Actividad;
use App\Models\Metodologia;
use App\Models\Tema;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Tema */
class StudentTutoringTopicResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $activities = $this->relationLoaded('actividades') ? $this->actividades : $this->actividades()->with('metodologias')->get();

        return [
            'id' => $this->getKey(),
            'tutoring_id' => $this->fk_asig_tutoria,
            'name' => $this->nombre,
            'description' => $this->descripcion,
            'is_active' => (bool) $this->estado,
            'is_covered' => (bool) $this->visto,
            'activities_count' => $activities->where('estado', true)->count(),
            'activities' => $activities->where('estado', true)->values()->map(function (Actividad $activity) {
                $methodologies = $activity->relationLoaded('metodologias') ? $activity->metodologias : $activity->metodologias()->get();

                return [
                    'id' => $activity->getKey(),
                    'topic_id' => $activity->fk_tema,
                    'name' => $activity->nombre,
                    'duration' => $activity->duracion,
                    'is_active' => (bool) $activity->estado,
                    'methodologies' => $methodologies->where('estado', true)->values()->map(fn (Metodologia $methodology) => [
                        'id' => $methodology->getKey(),
                        'activity_id' => $methodology->fk_actividad,
                        'description' => $methodology->descripcion,
                        'is_active' => (bool) $methodology->estado,
                    ]),
                ];
            }),
        ];
    }
}

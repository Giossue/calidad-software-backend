<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AsignaturaTutoria;
use App\Models\Horario;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Usuario */
class AvailableTutoringTeacherResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'name' => $this->nombre,
            'email' => $this->correo,
            'is_active' => $this->estado,
            'busy_schedules' => $this->whenLoaded('asignaturasTutoria', fn () => $this->asignaturasTutoria
                ->flatMap(fn (AsignaturaTutoria $tutoring): array => $tutoring->horarios->map(fn (Horario $schedule): array => [
                    'tutoring_id' => $tutoring->getKey(),
                    'tutoring_name' => $tutoring->nombre,
                    'day' => $schedule->dia_semana,
                    'start_time' => substr($schedule->hora_inicio, 0, 5),
                    'end_time' => substr($schedule->hora_fin, 0, 5),
                ])->all())->values()),
        ];
    }
}

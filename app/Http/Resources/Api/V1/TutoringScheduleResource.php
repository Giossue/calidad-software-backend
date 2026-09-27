<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Horario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Horario */
class TutoringScheduleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'tutoring_id' => $this->fk_asig_tutoria,
            'day' => $this->dia_semana,
            'start_time' => substr($this->hora_inicio, 0, 5),
            'end_time' => substr($this->hora_fin, 0, 5),
            'room' => $this->room,
            'is_active' => $this->estado,
        ];
    }
}

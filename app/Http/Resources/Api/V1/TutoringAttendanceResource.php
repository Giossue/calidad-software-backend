<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Asistencia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Asistencia */
class TutoringAttendanceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'enrollment_id' => $this->fk_inscripcion,
            'student_id' => $this->fk_id_usuario,
            'student_name' => $this->whenLoaded('estudiante', fn () => $this->estudiante->nombre),
            'date' => $this->fecha->toDateString(),
            'present' => $this->estado_asistencia,
        ];
    }
}

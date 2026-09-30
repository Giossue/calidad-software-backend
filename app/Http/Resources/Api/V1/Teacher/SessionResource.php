<?php

namespace App\Http\Resources\Api\V1\Teacher;

use App\Models\TutoringSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TutoringSession */
class SessionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(), 'tutoring_id' => $this->tutoring_id, 'date' => $this->date->toDateString(), 'topics_covered' => $this->topics_covered,
            'topics' => $this->topics->map(fn ($topic) => ['id' => $topic->getKey(), 'name' => $topic->nombre, 'is_active' => $topic->estado]),
            'attendance' => $this->attendance->map(fn ($record) => [
                'id' => $record->getKey(), 'enrollment_id' => $record->fk_inscripcion, 'student_id' => $record->fk_id_usuario,
                'student_name' => $record->estudiante->nombre, 'present' => $record->estado_asistencia,
            ]),
        ];
    }
}

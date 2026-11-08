<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Asistencia;
use App\Models\Tema;
use App\Models\TutoringSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una sesión de tutoría para la supervisión del coordinador: cuándo se dio, qué
 * temas se vieron y la lista de esa sesión.
 *
 * @mixin TutoringSession
 */
class TutoringSupervisionSessionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $attendance = $this->attendance->sortBy(fn (Asistencia $record): string => mb_strtolower($record->estudiante->nombre ?? ''))->values();

        return [
            'id' => $this->getKey(),
            'number' => $this->number(),
            'date' => $this->date->toDateString(),
            'topics_covered' => (bool) $this->topics_covered,
            'topics' => $this->topicsOfTheDay()->map(fn (Tema $topic): array => ['id' => $topic->getKey(), 'name' => $topic->nombre])->values(),
            'present_count' => $attendance->where('estado_asistencia', true)->count(),
            'absent_count' => $attendance->where('estado_asistencia', false)->count(),
            'attendance' => $attendance->map(fn (Asistencia $record): array => [
                'id' => $record->getKey(),
                'student_id' => $record->fk_id_usuario,
                'student_name' => $record->estudiante->nombre ?? '',
                'student_identification' => $record->estudiante->cedula ?? '',
                'present' => (bool) $record->estado_asistencia,
            ]),
        ];
    }
}

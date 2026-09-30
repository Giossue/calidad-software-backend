<?php

namespace App\Http\Resources\Api\V1\Teacher;

use App\Models\InscripcionTutoria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InscripcionTutoria */
class EnrollmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $student = $this->estudiante;
        $grades = $this->notas->sortByDesc('id_nota');

        return [
            'id' => $this->getKey(), 'tutoring_id' => $this->fk_asig_tutoria, 'student_id' => $this->fk_id_usuario,
            'identification' => $student->cedula, 'name' => $student->nombre, 'email' => $student->correo, 'phone' => $student->telefono,
            'is_active' => $this->estado, 'student_is_active' => $student->estado,
            'enrolled_at' => $this->fecha_inscripcion->toDateString(),
            'can_edit_profile' => $student->hasRole('estudiante') && $student->roleSlugs()->count() === 1,
            'diagnostic_grade' => $grades->firstWhere('tipo', 'diagnostic')?->valor,
            'partial_grade' => $grades->firstWhere('tipo', 'partial')?->valor,
            'second_partial_grade' => $grades->firstWhere('tipo', 'partial_two')?->valor,
            'knowledge_group' => $this->knowledgeMetric?->descripcion,
            'knowledge_group_key' => $this->knowledgeMetric?->rango,
        ];
    }
}

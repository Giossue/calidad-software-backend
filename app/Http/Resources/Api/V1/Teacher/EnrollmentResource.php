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
        $partial = $grades->firstWhere('tipo', 'partial')?->valor;
        $secondPartial = $grades->firstWhere('tipo', 'partial_two')?->valor;

        return [
            'id' => $this->getKey(), 'tutoring_id' => $this->fk_asig_tutoria, 'student_id' => $this->fk_id_usuario,
            'identification' => $student->cedula, 'name' => $student->nombre, 'email' => $student->correo, 'phone' => $student->telefono,
            'is_active' => $this->estado, 'student_is_active' => $student->estado,
            'enrolled_at' => $this->fecha_inscripcion->toDateString(),
            'enrollment_number' => $this->numero_matricula,
            'attendance_required' => $this->requiresAttendance(),
            'can_edit_profile' => $student->hasRole('estudiante') && $student->roleSlugs()->count() === 1,
            'diagnostic_grade' => $grades->firstWhere('tipo', 'diagnostic')?->valor,
            'partial_grade' => $partial,
            'second_partial_grade' => $secondPartial,
            'final_result' => $this->final_result,
            'is_abandoned' => $this->isAbandoned(),
            'abandoned_at' => $this->abandoned_at?->toIso8601String(),
            'abandonment_reason' => $this->abandonment_reason,
            // Lo que corresponde a las notas: promedio de los dos parciales frente a la nota mínima.
            'suggested_result' => InscripcionTutoria::resultForPartials($partial, $secondPartial),
            'knowledge_group' => $this->knowledgeMetric?->descripcion,
            'knowledge_group_key' => $this->knowledgeMetric?->rango,
        ];
    }
}

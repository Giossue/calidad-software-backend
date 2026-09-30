<?php

namespace App\Http\Resources\Api\V1;

use App\Models\InscripcionTutoria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InscripcionTutoria */
class StudentTutoringGradeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $subject = $this->asignaturaTutoria;
        $period = $subject?->periodo;
        $section = $subject?->paralelo;
        $cycle = $subject?->ciclo;
        $teacher = $subject?->docente;
        $metric = $this->knowledgeMetric;
        $grades = ($this->relationLoaded('notas') ? $this->notas : $this->notas()->get())->sortByDesc('id_nota');

        $diagnosticGrade = $grades->firstWhere('tipo', 'diagnostic');
        $partialGrade = $grades->firstWhere('tipo', 'partial');
        $secondPartialGrade = $grades->firstWhere('tipo', 'partial_two');

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
                    'is_active' => $period->estado,
                ] : null,
                'section' => $section ? [
                    'id' => $section->getKey(),
                    'name' => $section->nombre,
                ] : null,
                'cycle' => $cycle ? [
                    'id' => $cycle->getKey(),
                    'name' => $cycle->nombre,
                    'number' => $cycle->numero,
                ] : null,
                'teacher' => $teacher ? [
                    'id' => $teacher->getKey(),
                    'name' => $teacher->nombre,
                    'email' => $teacher->correo,
                    'phone' => $teacher->telefono,
                ] : null,
            ] : null,
            'grades' => [
                'diagnostic' => $diagnosticGrade ? [
                    'id' => $diagnosticGrade->getKey(),
                    'value' => (float) $diagnosticGrade->valor,
                    'formatted_value' => number_format((float) $diagnosticGrade->valor, 2),
                    'registered_at' => $diagnosticGrade->fecha_registro?->toDateString(),
                ] : null,
                'partial' => $partialGrade ? [
                    'id' => $partialGrade->getKey(),
                    'value' => (float) $partialGrade->valor,
                    'formatted_value' => number_format((float) $partialGrade->valor, 2),
                    'registered_at' => $partialGrade->fecha_registro?->toDateString(),
                ] : null,
                'second_partial' => $secondPartialGrade ? [
                    'id' => $secondPartialGrade->getKey(),
                    'value' => (float) $secondPartialGrade->valor,
                    'formatted_value' => number_format((float) $secondPartialGrade->valor, 2),
                    'registered_at' => $secondPartialGrade->fecha_registro?->toDateString(),
                ] : null,
            ],
            'knowledge_metric' => $metric ? [
                'id' => $metric->getKey(),
                'group' => $metric->descripcion,
                'group_key' => $metric->rango,
                'min_score' => (float) $metric->nota_minima,
                'max_score' => (float) $metric->nota_maxima,
            ] : null,
            'scale_settings' => [
                'minimum' => config('teaching.grade_min', 0),
                'maximum' => config('teaching.grade_max', 10),
                'groups' => config('teaching.groups', []),
            ],
        ];
    }
}

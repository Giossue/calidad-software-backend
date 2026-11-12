<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\Api\V1\Teacher\EnrollmentResource;
use App\Models\InscripcionTutoria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Notas de un estudiante inscrito, una entrada por etapa, para la consulta del coordinador.
 * Las notas y el resultado se leen del recurso del docente para no repetir su cálculo.
 *
 * @mixin InscripcionTutoria
 */
class TutoringGradeResource extends JsonResource
{
    /** Etapas en el orden y con las etiquetas que muestra el docente. */
    public const STAGES = [
        'diagnostic' => ['label' => 'Diagnóstico', 'field' => 'diagnostic_grade'],
        'partial' => ['label' => 'Parcial 1', 'field' => 'partial_grade'],
        'partial_two' => ['label' => 'Parcial 2', 'field' => 'second_partial_grade'],
    ];

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $enrollment = EnrollmentResource::make($this->resource)->toArray($request);

        return [
            'enrollment_id' => $enrollment['id'],
            'student_id' => $enrollment['student_id'],
            'identification' => $enrollment['identification'],
            'name' => $enrollment['name'],
            'grades' => array_map(
                fn (string $stage, array $definition) => ['stage' => $stage, 'label' => $definition['label'], 'value' => $enrollment[$definition['field']]],
                array_keys(self::STAGES), self::STAGES,
            ),
            'final_result' => $enrollment['final_result'],
            'suggested_result' => $enrollment['suggested_result'],
        ];
    }
}

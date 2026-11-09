<?php

namespace App\Actions\Teacher;

use App\Models\AsignaturaTutoria;
use App\Models\Reporte;
use App\Models\Usuario;
use App\Support\TeacherWorkspace;
use Illuminate\Validation\ValidationException;

/**
 * Arma las "Calificaciones Evaluación Diagnóstica" (formato 1.a): encabezado, la lista
 * de estudiantes con su cédula y calificación sobre 10 ("No asiste" sin nota) y la
 * firma del docente. Usa las mismas calificaciones que el reporte diagnóstico.
 */
class DiagnosticGradesReport
{
    public const TYPE = 'Calificaciones de la evaluación diagnóstica';

    public const FORMAT = 'diagnostic_grades';

    public function __construct(private TeacherWorkspace $workspace, private DiagnosticEvaluationReport $diagnostic) {}

    /** @return array<string, mixed> */
    public function build(Usuario $teacher, AsignaturaTutoria $tutoring): array
    {
        $diagnostic = $this->diagnostic->build($teacher, $tutoring, today()->toDateString());

        return [
            'format' => self::FORMAT,
            'faculty' => $diagnostic['faculty'],
            'career' => $diagnostic['career'],
            'subject' => $diagnostic['subject'],
            'cycle' => $diagnostic['cycle'],
            'cycle_name' => $diagnostic['cycle_name'],
            'academic_period' => $diagnostic['academic_period'],
            'section' => $diagnostic['section'],
            'teacher' => $teacher->nombre,
            'students_listed' => $diagnostic['students_listed'],
            'students_evaluated' => $diagnostic['students_evaluated'],
            'grades' => array_map(fn (array $row): array => [
                'number' => $row['number'],
                'identification' => $row['identification'],
                'name' => $row['name'],
                'grade' => $row['grade'],
            ], $diagnostic['grades']),
        ];
    }

    public function send(Usuario $teacher, AsignaturaTutoria $tutoring): Reporte
    {
        return $this->workspace->write($teacher, $tutoring, function (AsignaturaTutoria $locked) use ($teacher): Reporte {
            $summary = $this->build($teacher, $locked);

            if ($summary['students_evaluated'] === 0) {
                throw ValidationException::withMessages([
                    'report' => 'Registra al menos una calificación diagnóstica antes de enviar las calificaciones.',
                ]);
            }

            $content = $this->content($summary);
            $previous = $locked->reportes()->where('fk_id_usuario', $teacher->getKey())->where('tipo_reporte', self::TYPE)->orderByDesc('id_reporte')->first();
            if ($previous && $previous->content === $content && $previous->summary == $summary) {
                return $previous->load('generadoPor');
            }

            return Reporte::query()->create([
                'fk_asig_tutoria' => $locked->getKey(), 'fk_id_usuario' => $teacher->getKey(),
                'tipo_reporte' => self::TYPE, 'fecha_generacion' => now(), 'content' => $content, 'summary' => $summary,
            ])->load('generadoPor');
        });
    }

    /**
     * Versión en texto para la búsqueda y los clientes que no conocen el formato.
     *
     * @param  array<string, mixed>  $summary
     */
    private function content(array $summary): string
    {
        $number = fn (float|int $value): string => rtrim(rtrim(number_format((float) $value, 2, ',', ''), '0'), ',');

        $lines = [
            'FACULTAD: '.($summary['faculty'] ?? ''),
            'CARRERA: '.($summary['career'] ?? ''),
            'CALIFICACIONES EVALUACIÓN DIAGNÓSTICA',
            'Período académico: '.($summary['academic_period'] ?? ''),
            'Docente: '.$summary['teacher'],
            'Asignatura: '.$summary['subject'],
            'Ciclo: '.($summary['cycle_name'] ?? $summary['cycle'] ?? '').'; Paralelo: '.($summary['section'] ?? ''),
            '',
        ];

        foreach ($summary['grades'] as $row) {
            $grade = $row['grade'] === null ? 'No asiste' : $number($row['grade']);
            $lines[] = "{$row['number']}. {$row['identification']} {$row['name']}: {$grade}";
        }

        return implode("\n", $lines);
    }
}

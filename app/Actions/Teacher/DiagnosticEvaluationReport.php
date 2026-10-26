<?php

namespace App\Actions\Teacher;

use App\Models\AsignaturaTutoria;
use App\Models\InscripcionTutoria;
use App\Models\Nota;
use App\Models\Reporte;
use App\Models\Usuario;
use App\Support\KnowledgeScale;
use App\Support\TeacherWorkspace;
use App\Support\TutoringReportHeader;
use Illuminate\Validation\ValidationException;

/**
 * Arma el "Reporte de la Evaluación Diagnóstica" con la estructura del formato oficial:
 * encabezado, estudiantes en la lista y en el aula, número y porcentaje por métrica de
 * conocimiento con su plan de acción, observaciones, anexo de calificaciones y firmas.
 */
class DiagnosticEvaluationReport
{
    public const TYPE = 'Reporte de la evaluación diagnóstica';

    public const FORMAT = 'diagnostic_evaluation';

    public function __construct(private TeacherWorkspace $workspace) {}

    /** @return array<string, mixed> */
    public function build(Usuario $teacher, AsignaturaTutoria $tutoring, string $date, ?string $observations = null): array
    {
        $enrollments = $tutoring->inscripciones()
            ->where('estado', true)
            ->with(['estudiante', 'notas' => fn ($query) => $query->where('tipo', 'diagnostic')->orderByDesc('id_nota')])
            ->get()
            ->sortBy(fn (InscripcionTutoria $enrollment) => mb_strtoupper($enrollment->estudiante->nombre))
            ->values();

        $grades = $enrollments->map(function (InscripcionTutoria $enrollment, int $index): array {
            /** @var Nota|null $grade */
            $grade = $enrollment->notas->first();
            $value = $grade === null ? null : (float) $grade->valor;

            return [
                'number' => $index + 1,
                'identification' => $enrollment->estudiante->cedula,
                'name' => $enrollment->estudiante->nombre,
                'grade' => $value,
                'group' => $value === null ? null : KnowledgeScale::classify($value)['label'] ?? null,
            ];
        });

        $evaluated = $grades->whereNotNull('grade')->count();
        $groups = collect(KnowledgeScale::groups())->map(function (array $group) use ($grades, $evaluated): array {
            $count = $grades->where('group', $group['label'])->count();

            return [
                'key' => $group['key'],
                'label' => $group['label'],
                'range_label' => $group['range_label'],
                'count' => $count,
                'percentage' => $evaluated > 0 ? round($count / $evaluated * 100, 2) : 0.0,
                'action_plan' => $group['action_plan'],
            ];
        })->all();

        return [
            'format' => self::FORMAT,
            ...TutoringReportHeader::for($tutoring),
            'date' => $date,
            'teacher' => $teacher->nombre,
            'students_listed' => $enrollments->count(),
            'students_evaluated' => $evaluated,
            'groups' => $groups,
            'observations' => filled($observations) ? trim((string) $observations) : null,
            'grades' => $grades->all(),
        ];
    }

    /** @param array{date?: string|null, observations?: string|null} $data */
    public function send(Usuario $teacher, AsignaturaTutoria $tutoring, array $data): Reporte
    {
        return $this->workspace->write($teacher, $tutoring, function (AsignaturaTutoria $locked) use ($teacher, $data): Reporte {
            $summary = $this->build($teacher, $locked, $data['date'] ?? today()->toDateString(), $data['observations'] ?? null);

            if ($summary['students_evaluated'] === 0) {
                throw ValidationException::withMessages([
                    'report' => 'Registra al menos una calificación diagnóstica antes de enviar el reporte.',
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
     * Versión en texto del reporte: permite buscarlo y leerlo en clientes que no
     * conocen el formato estructurado.
     *
     * @param  array<string, mixed>  $summary
     */
    private function content(array $summary): string
    {
        $number = fn (float|int $value): string => rtrim(rtrim(number_format((float) $value, 2, ',', ''), '0'), ',');

        $lines = [
            'FACULTAD: '.($summary['faculty'] ?? ''),
            'CARRERA: '.($summary['career'] ?? ''),
            'REPORTE DE LA EVALUACIÓN DIAGNÓSTICA',
            'Fecha: '.$summary['date'],
            'Docente: '.$summary['teacher'],
            'Asignatura: '.$summary['subject'],
            'Ciclo: '.($summary['cycle'] ?? $summary['cycle_name'] ?? ''),
            'Período académico: '.($summary['academic_period'] ?? ''),
            'Paralelo: '.($summary['section'] ?? ''),
            '',
            'Parámetros de la evaluación al estudiante',
            'Nº de estudiantes en la lista: '.$summary['students_listed'].'; en el aula: '.$summary['students_evaluated'],
        ];

        foreach ($summary['groups'] as $group) {
            $lines[] = "{$group['label']} ({$group['range_label']}): {$group['count']} estudiantes, {$number($group['percentage'])}%. Plan de acción: ".implode('; ', $group['action_plan']).'.';
        }

        $lines[] = '';
        $lines[] = 'Observaciones: '.($summary['observations'] ?? 'Sin observaciones.');
        $lines[] = '';
        $lines[] = 'Calificaciones de la evaluación diagnóstica:';
        foreach ($summary['grades'] as $row) {
            $grade = $row['grade'] === null ? 'No asiste' : $number($row['grade']);
            $lines[] = "{$row['number']}. {$row['identification']} {$row['name']}: {$grade}";
        }

        return implode("\n", $lines);
    }
}

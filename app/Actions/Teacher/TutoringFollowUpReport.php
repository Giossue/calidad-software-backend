<?php

namespace App\Actions\Teacher;

use App\Models\Actividad;
use App\Models\AsignaturaTutoria;
use App\Models\Asistencia;
use App\Models\InscripcionTutoria;
use App\Models\Metodologia;
use App\Models\Nota;
use App\Models\Reporte;
use App\Models\Tema;
use App\Models\TutoringSession;
use App\Models\Usuario;
use App\Support\KnowledgeScale;
use App\Support\TeacherWorkspace;
use App\Support\TutoringReportHeader;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Arma el "Reporte de Seguimiento a las Tutorías Académicas" de un parcial con la
 * estructura del formato oficial: datos informativos, antecedentes, actividades
 * programadas, número de estudiantes que asisten, estudiantes que mejoraron su
 * rendimiento, observación y firmas.
 */
class TutoringFollowUpReport
{
    public const FORMAT = 'tutoring_follow_up';

    /** Grupos de la evaluación diagnóstica que se consideran en riesgo académico. */
    public const RISK_GROUPS = ['low', 'medium'];

    private const PARTIALS = [
        1 => ['grade' => 'partial', 'label' => 'primer parcial', 'title' => 'Primer Parcial'],
        2 => ['grade' => 'partial_two', 'label' => 'segundo parcial', 'title' => 'Segundo Parcial'],
    ];

    public function __construct(private TeacherWorkspace $workspace) {}

    public static function type(int $partial): string
    {
        return 'Reporte de seguimiento de tutorías ('.self::PARTIALS[$partial]['label'].')';
    }

    /**
     * @param  array{partial: int, from: string, to: string, date: string, observations?: string|null}  $data
     * @return array<string, mixed>
     */
    public function build(Usuario $teacher, AsignaturaTutoria $tutoring, array $data): array
    {
        $partial = self::PARTIALS[$data['partial']];
        $header = TutoringReportHeader::for($tutoring);
        $observations = $data['observations'] ?? null;

        $enrollments = $tutoring->inscripciones()
            ->where('estado', true)
            ->with(['estudiante', 'notas' => fn ($query) => $query->whereIn('tipo', ['diagnostic', $partial['grade']])->orderByDesc('id_nota')])
            ->get()
            ->sortBy(fn (InscripcionTutoria $enrollment) => mb_strtoupper($enrollment->estudiante->nombre))
            ->values();

        $attending = Asistencia::query()
            ->whereIn('fk_inscripcion', $enrollments->modelKeys())
            ->where('estado_asistencia', true)
            ->whereDate('fecha', '>=', $data['from'])
            ->whereDate('fecha', '<=', $data['to'])
            ->distinct()
            ->pluck('fk_inscripcion')
            ->flip();

        $students = $enrollments->map(function (InscripcionTutoria $enrollment) use ($partial, $attending): array {
            $grade = fn (string $type): ?float => ($nota = $enrollment->notas->firstWhere('tipo', $type)) instanceof Nota ? (float) $nota->valor : null;
            $diagnostic = $grade('diagnostic');

            return [
                'name' => $enrollment->estudiante->nombre,
                'diagnostic' => $diagnostic,
                'partial' => $grade($partial['grade']),
                'group' => $diagnostic === null ? null : KnowledgeScale::classify($diagnostic)['key'] ?? null,
                'attended' => $attending->has($enrollment->getKey()),
            ];
        });

        $atRisk = $students->filter(fn (array $student) => in_array($student['group'], self::RISK_GROUPS, true));
        $notAtRisk = $students->filter(fn (array $student) => $student['group'] !== null && ! in_array($student['group'], self::RISK_GROUPS, true));

        $improved = $students
            ->filter(fn (array $student) => $student['diagnostic'] !== null && $student['partial'] !== null && $student['partial'] > $student['diagnostic'])
            ->values()
            ->map(fn (array $student, int $index) => [
                'number' => $index + 1,
                'name' => $student['name'],
                'diagnostic' => $student['diagnostic'],
                'partial' => $student['partial'],
            ]);

        $sessions = TutoringSession::query()
            ->where('tutoring_id', $tutoring->getKey())
            ->whereDate('date', '>=', $data['from'])
            ->whereDate('date', '<=', $data['to'])
            ->with(['topics' => fn ($query) => $query->orderBy('id_tema'), 'topics.actividades' => fn ($query) => $query->where('estado', true)->orderBy('id_actividad'), 'topics.actividades.metodologias' => fn ($query) => $query->where('estado', true)->orderBy('id_metodologia')])
            ->orderBy('date')
            ->get();

        $activities = $sessions->flatMap(fn (TutoringSession $session) => $session->topics->flatMap(function (Tema $topic) use ($session): array {
            $row = fn (?Actividad $activity): array => [
                'date' => $session->date->toDateString(),
                'topic' => $topic->nombre,
                'activity' => $activity?->nombre,
                'methodology' => $activity?->metodologias->map(fn (Metodologia $methodology) => $methodology->descripcion)->implode('; ') ?: null,
            ];

            return $topic->actividades->isEmpty() ? [$row(null)] : $topic->actividades->map($row)->all();
        }))->values();

        return [
            'format' => self::FORMAT,
            ...$header,
            'teacher' => $teacher->nombre,
            'date' => $data['date'],
            'partial' => $data['partial'],
            'partial_label' => $partial['label'],
            'partial_title' => $partial['title'],
            'from' => $data['from'],
            'to' => $data['to'],
            'background' => 'Tras la evaluación diagnóstica inicial, se implementaron tutorías individuales y grupales en la CARRERA DE '
                .mb_strtoupper((string) ($header['career'] ?? '')).' como apoyo pedagógico y fortalecimiento académico. '
                .'Estas acciones fortalecen el indicador 11 del Modelo genérico para la evaluación del entorno de aprendizaje de carreras de grado, '
                .'pues el rendimiento académico refleja el cumplimiento de aprendizajes. Así, estas asesorías generan estrategias de acompañamiento '
                .'oportuno para disminuir significativamente la reprobación y deserción durante el proceso formativo.',
            'session_count' => $sessions->count(),
            'activities' => $activities->all(),
            'attendance' => [
                'at_risk_total' => $atRisk->count(),
                'at_risk_attending' => $atRisk->where('attended', true)->count(),
                'not_at_risk_attending' => $notAtRisk->where('attended', true)->count(),
                'unclassified_attending' => $students->whereNull('group')->where('attended', true)->count(),
            ],
            'improved' => $improved->all(),
            'observations' => filled($observations) ? trim((string) $observations) : null,
        ];
    }

    /** @param array{partial: int, from: string, to: string, date: string, observations?: string|null} $data */
    public function send(Usuario $teacher, AsignaturaTutoria $tutoring, array $data): Reporte
    {
        return $this->workspace->write($teacher, $tutoring, function (AsignaturaTutoria $locked) use ($teacher, $data): Reporte {
            $summary = $this->build($teacher, $locked, $data);

            if ($summary['session_count'] === 0) {
                throw ValidationException::withMessages([
                    'report' => 'No hay tutorías registradas entre las fechas indicadas.',
                ]);
            }

            $type = self::type($data['partial']);
            $content = $this->content($summary);
            $previous = $locked->reportes()->where('fk_id_usuario', $teacher->getKey())->where('tipo_reporte', $type)->orderByDesc('id_reporte')->first();
            if ($previous && $previous->content === $content && $previous->summary == $summary) {
                return $previous->load('generadoPor');
            }

            return Reporte::query()->create([
                'fk_asig_tutoria' => $locked->getKey(), 'fk_id_usuario' => $teacher->getKey(),
                'tipo_reporte' => $type, 'fecha_generacion' => now(), 'content' => $content, 'summary' => $summary,
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
        $number = fn (float $value): string => number_format($value, 2, ',', '');
        $subject = $summary['subject'];
        $presented = Carbon::parse($summary['date']);
        $presented->locale('es');
        $attendance = $summary['attendance'];

        $lines = [
            'REPORTE DE SEGUIMIENTO A LAS TUTORÍAS ACADÉMICAS',
            'Facultad: '.($summary['faculty'] ?? ''),
            'Carrera: '.($summary['career'] ?? ''),
            'Periodo académico: '.($summary['academic_period'] ?? ''),
            'Docente: '.$summary['teacher'],
            'Fecha de presentación: '.$presented->translatedFormat('j \d\e F \d\e\l Y'),
            '',
            'ANTECEDENTES',
            $summary['background'],
            '',
            'ACTIVIDADES PROGRAMADAS',
            "Durante el {$summary['partial_label']} en la asignatura de {$subject} se revisó las siguientes temáticas:",
        ];
        foreach ($summary['activities'] as $row) {
            $lines[] = '• '.str_replace('-', '/', $row['date'])." | {$row['topic']} | ".($row['activity'] ?? '—').' | '.($row['methodology'] ?? '—');
        }

        $lines[] = '';
        $lines[] = 'NÚMERO DE ESTUDIANTES QUE ASISTEN A TUTORÍAS';
        $lines[] = 'Total estudiantes con riesgo académico: '.$attendance['at_risk_total'];
        $lines[] = '# de estudiantes con riesgo académico que asisten: '.$attendance['at_risk_attending'];
        $lines[] = '# de estudiantes sin riesgo académico que asisten: '.$attendance['not_at_risk_attending'];

        $lines[] = '';
        $lines[] = 'ESTUDIANTES QUE MEJORARON SU RENDIMIENTO';
        foreach ($summary['improved'] as $row) {
            $lines[] = "{$row['number']}. {$row['name']}: diagnóstica {$number($row['diagnostic'])}; {$summary['partial_label']} {$number($row['partial'])}";
        }
        if ($summary['improved'] === []) {
            $lines[] = 'Ningún estudiante registra mejora entre la evaluación diagnóstica y el '.$summary['partial_label'].'.';
        }

        $lines[] = '';
        $lines[] = 'OBSERVACIÓN: '.($summary['observations'] ?? 'Sin observaciones.');

        return implode("\n", $lines);
    }
}

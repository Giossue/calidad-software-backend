<?php

namespace App\Actions\Teacher;

use App\Models\AsignaturaTutoria;
use App\Models\Asistencia;
use App\Models\Horario;
use App\Models\InscripcionTutoria;
use App\Models\Nota;
use App\Models\Reporte;
use App\Models\Tema;
use App\Models\TutoringSession;
use App\Models\Usuario;
use App\Support\KnowledgeScale;
use App\Support\TeacherWorkspace;
use App\Support\TutoringReportHeader;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;

/**
 * Arma el "Informe final PAO" (informe de ejecución de tutorías académicas) del docente
 * con la estructura del formato oficial: datos generales, información general,
 * antecedentes con horario, matriz de operacionalización por asignatura, conclusiones,
 * recomendaciones, anexos (mejora al segundo parcial y registro de tutorías) y firmas.
 *
 * Reglas de la matriz (decididas con coordinación):
 * - Riesgo académico: diagnóstico Bajo o Medio.
 * - Asiste: al menos una asistencia como presente en la tutoría.
 * - Aprobó: promedio de los dos parciales ≥ teaching.passing_grade.
 * - Mejoró: nota del segundo parcial mayor que la del primero.
 * - % Efectividad: con riesgo que asistieron y aprobaron ÷ con riesgo que asisten.
 */
class FinalPaoReport
{
    public const FORMAT = 'final_pao';

    private const DAYS = [1 => 'lunes', 2 => 'martes', 3 => 'miercoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sabado', 7 => 'domingo'];

    private const DAY_LABELS = ['lunes' => 'Lunes', 'martes' => 'Martes', 'miercoles' => 'Miércoles', 'jueves' => 'Jueves', 'viernes' => 'Viernes', 'sabado' => 'Sábado', 'domingo' => 'Domingo'];

    public function __construct(private TeacherWorkspace $workspace) {}

    /**
     * Tutorías del docente que pueden integrar el informe: mismo período y carrera que la de origen.
     *
     * @return Collection<int, AsignaturaTutoria>
     */
    public static function eligibleTutorings(Usuario $teacher, AsignaturaTutoria $origin): Collection
    {
        $origin->loadMissing('ciclo');

        return AsignaturaTutoria::query()
            ->where('fk_docente', $teacher->getKey())
            ->where('fk_periodo', $origin->fk_periodo)
            ->whereHas('ciclo', fn ($query) => $query->where('fk_carrera', $origin->ciclo?->fk_carrera))
            ->with(['ciclo', 'paralelo'])
            ->orderBy('nombre')
            ->orderBy('id_asig_tutoria')
            ->get();
    }

    public static function type(AsignaturaTutoria $origin): string
    {
        $origin->loadMissing('periodo');

        return mb_substr('Informe final de tutorías PAO '.($origin->periodo->nombre ?? ''), 0, 100);
    }

    /**
     * @param  array{date: string, report_number?: string|null, tutoring_ids: list<int>, observations?: array<int|string, string|null>, conclusions?: string|null, recommendations?: string|null}  $data
     * @return array<string, mixed>
     */
    public function build(Usuario $teacher, AsignaturaTutoria $origin, array $data): array
    {
        $eligible = self::eligibleTutorings($teacher, $origin);
        $tutorings = $eligible->whereIn('id_asig_tutoria', $data['tutoring_ids'])->values();
        $header = TutoringReportHeader::for($origin);
        $coordinator = $origin->ciclo?->carrera?->coordinators->firstWhere('estado', true);
        $observations = $data['observations'] ?? [];
        $passing = (float) Config::get('teaching.passing_grade', 7);

        $tutorings->load([
            'horarios' => fn ($query) => $query->where('estado', true)->orderBy('id_horario'),
            'inscripciones' => fn ($query) => $query->where('estado', true),
            'inscripciones.estudiante',
            'inscripciones.notas' => fn ($query) => $query->orderByDesc('id_nota'),
        ]);

        $rows = [];
        $improved = [];
        $register = [];
        $schedule = [];
        foreach ($tutorings as $tutoring) {
            $slots = $tutoring->horarios->map(fn (Horario $slot) => [
                'day' => mb_strtolower((string) $slot->dia_semana),
                'start' => substr((string) $slot->hora_inicio, 0, 5),
                'end' => substr((string) $slot->hora_fin, 0, 5),
                'hours' => $this->hours((string) $slot->hora_inicio, (string) $slot->hora_fin),
                'room' => $slot->room,
            ]);
            foreach ($slots as $slot) {
                $schedule[] = ['subject' => $tutoring->nombre, 'section' => $tutoring->paralelo?->nombre, ...collect($slot)->except('hours')->all()];
            }

            $sessions = TutoringSession::query()->where('tutoring_id', $tutoring->getKey())
                ->with(['topics' => fn ($query) => $query->orderBy('id_tema'), 'attendance'])
                ->orderBy('date')->get();
            $averageSlot = $slots->isEmpty() ? 0.0 : $slots->avg('hours');
            $hoursDone = $sessions->sum(function (TutoringSession $session) use ($slots, $averageSlot): float {
                $matching = $slots->where('day', self::DAYS[$session->date->dayOfWeekIso]);

                return $matching->isEmpty() ? (float) $averageSlot : (float) $matching->sum('hours');
            });

            $attending = Asistencia::query()
                ->whereIn('fk_inscripcion', $tutoring->inscripciones->modelKeys())
                ->where('estado_asistencia', true)
                ->distinct()->pluck('fk_inscripcion')->flip();

            $students = $tutoring->inscripciones
                ->sortBy(fn (InscripcionTutoria $enrollment) => mb_strtoupper($enrollment->estudiante->nombre))
                ->values()
                ->map(function (InscripcionTutoria $enrollment) use ($attending, $passing): array {
                    $grade = fn (string $type): ?float => ($nota = $enrollment->notas->firstWhere('tipo', $type)) instanceof Nota ? (float) $nota->valor : null;
                    $diagnostic = $grade('diagnostic');
                    $first = $grade('partial');
                    $second = $grade('partial_two');

                    return [
                        'name' => $enrollment->estudiante->nombre,
                        'at_risk' => $diagnostic !== null && in_array(KnowledgeScale::classify($diagnostic)['key'] ?? null, TutoringFollowUpReport::RISK_GROUPS, true),
                        'attended' => $attending->has($enrollment->getKey()),
                        'passed' => $first !== null && $second !== null && ($first + $second) / 2 >= $passing,
                        'improved' => $first !== null && $second !== null && $second > $first,
                        'first' => $first,
                        'second' => $second,
                    ];
                });

            $atRisk = $students->where('at_risk', true);
            $atRiskAttending = $atRisk->where('attended', true);
            $atRiskPassed = $atRiskAttending->where('passed', true)->count();

            $rows[] = [
                'tutoring_id' => $tutoring->getKey(),
                'subject' => $tutoring->nombre,
                'cycle' => $tutoring->ciclo?->numero,
                'cycle_name' => $tutoring->ciclo?->nombre,
                'section' => $tutoring->paralelo?->nombre,
                'weekly_hours' => round((float) $slots->sum('hours'), 2),
                'hours_done' => round((float) $hoursDone, 2),
                'enrolled' => $students->count(),
                'at_risk' => $atRisk->count(),
                'at_risk_attending' => $atRiskAttending->count(),
                'at_risk_attending_passed' => $atRiskPassed,
                'at_risk_improved' => $atRisk->where('improved', true)->count(),
                'effectiveness' => $atRiskAttending->isEmpty() ? null : round($atRiskPassed / $atRiskAttending->count() * 100, 2),
                'observation' => filled($observations[$tutoring->getKey()] ?? null) ? trim((string) $observations[$tutoring->getKey()]) : null,
            ];

            $improved[] = [
                'subject' => $tutoring->nombre,
                'section' => $tutoring->paralelo?->nombre,
                'students' => $students->where('improved', true)->values()->map(fn (array $student, int $index) => [
                    'number' => $index + 1, 'name' => $student['name'], 'first' => $student['first'], 'second' => $student['second'],
                ])->all(),
            ];

            $register[] = [
                'subject' => $tutoring->nombre,
                'section' => $tutoring->paralelo?->nombre,
                'sessions' => $sessions->map(fn (TutoringSession $session) => [
                    'date' => $session->date->toDateString(),
                    'topics' => $session->topics->map(fn (Tema $topic) => $topic->nombre)->all(),
                    'present' => $session->attendance->where('estado_asistencia', true)->count(),
                    'total' => $session->attendance->count(),
                ])->all(),
            ];
        }

        $period = $header['academic_period'] ?? '';
        $career = $header['career'] ?? '';

        return [
            'format' => self::FORMAT,
            'faculty' => $header['faculty'],
            'career' => $career,
            'academic_period' => $period,
            'date' => $data['date'],
            'report_number' => filled($data['report_number'] ?? null) ? trim((string) $data['report_number']) : $this->suggestedNumber($teacher, $origin, $data['date']),
            'teacher' => ['name' => $teacher->nombre, 'email' => $teacher->correo, 'extension' => null, 'position' => 'Docente'],
            'coordinator' => ['name' => $coordinator?->nombre, 'email' => $coordinator?->correo, 'extension' => null, 'position' => 'Coordinador/a de Carrera'],
            'subject_line' => 'Informe de ejecución de tutorías académicas PAO '.$period,
            'objective' => 'Presentar los resultados de las tutorías académicas ejecutadas en las asignaturas de '
                .$this->list($tutorings->map(fn (AsignaturaTutoria $tutoring): string => $tutoring->nombre)->unique()->all())." de la carrera de {$career} en el periodo académico {$period}.",
            'background' => 'Basado en la normativa vigente, la tutoría académica se consolida como un mecanismo estratégico para garantizar la permanencia '
                ."y progresión estudiantil. En la carrera de {$career}, este acompañamiento fortalece el aprendizaje, mitigando riesgos de deserción "
                .'y asegurando la calidad educativa mediante un seguimiento sistemático que favorece la titulación oportuna y el éxito formativo.',
            'schedule' => $schedule,
            'matrix' => $rows,
            'conclusions' => filled($data['conclusions'] ?? null) ? trim((string) $data['conclusions']) : null,
            'recommendations' => filled($data['recommendations'] ?? null) ? trim((string) $data['recommendations']) : null,
            'improved' => $improved,
            'register' => $register,
            'passing_grade' => $passing,
            'available_tutorings' => $eligible->map(fn (AsignaturaTutoria $tutoring) => [
                'id' => $tutoring->getKey(), 'subject' => $tutoring->nombre, 'cycle' => $tutoring->ciclo?->numero, 'section' => $tutoring->paralelo?->nombre,
            ])->all(),
        ];
    }

    /** @param array{date: string, report_number?: string|null, tutoring_ids: list<int>, observations?: array<int|string, string|null>, conclusions?: string|null, recommendations?: string|null} $data */
    public function send(Usuario $teacher, AsignaturaTutoria $origin, array $data): Reporte
    {
        return $this->workspace->write($teacher, $origin, function (AsignaturaTutoria $locked) use ($teacher, $data): Reporte {
            $eligibleIds = self::eligibleTutorings($teacher, $locked)->modelKeys();
            if (array_diff($data['tutoring_ids'], $eligibleIds) !== [] || ! in_array($locked->getKey(), $data['tutoring_ids'], true)) {
                throw ValidationException::withMessages([
                    'tutoring_ids' => 'Selecciona tutorías propias del mismo período y carrera, incluida la tutoría actual.',
                ]);
            }

            $summary = $this->build($teacher, $locked, $data);
            unset($summary['available_tutorings']);

            $type = self::type($locked);
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
     * Número sugerido: el ya usado en el informe de este PAO o, si es el primero, el
     * correlativo del docente en el año (01-TA-2026, 02-TA-2026…).
     */
    private function suggestedNumber(Usuario $teacher, AsignaturaTutoria $origin, string $date): string
    {
        $type = self::type($origin);
        $previous = Reporte::query()->where('fk_id_usuario', $teacher->getKey())->where('tipo_reporte', $type)->orderByDesc('id_reporte')->first();
        if (is_string($previous?->summary['report_number'] ?? null)) {
            return $previous->summary['report_number'];
        }

        $year = Carbon::parse($date)->year;
        $count = Reporte::query()
            ->where('fk_id_usuario', $teacher->getKey())
            ->where('tipo_reporte', 'like', 'Informe final de tutorías PAO%')
            ->whereYear('fecha_generacion', $year)
            ->distinct()
            ->count('tipo_reporte');

        return str_pad((string) ($count + 1), 2, '0', STR_PAD_LEFT)."-TA-{$year}";
    }

    private function hours(string $start, string $end): float
    {
        $from = Carbon::createFromFormat('H:i', substr($start, 0, 5));
        $to = Carbon::createFromFormat('H:i', substr($end, 0, 5));

        return $from && $to && $to->greaterThan($from) ? round($from->diffInMinutes($to) / 60, 2) : 0.0;
    }

    /** @param array<int, string> $items */
    private function list(array $items): string
    {
        $items = array_values($items);
        if (count($items) <= 1) {
            return $items[0] ?? '';
        }

        return implode(', ', array_slice($items, 0, -1)).' y '.end($items);
    }

    /**
     * Versión en texto del informe: permite buscarlo y leerlo en clientes que no
     * conocen el formato estructurado.
     *
     * @param  array<string, mixed>  $summary
     */
    private function content(array $summary): string
    {
        $number = fn (?float $value): string => $value === null ? '—' : rtrim(rtrim(number_format($value, 2, ',', ''), '0'), ',');

        $lines = [
            'INFORME GENERAL — '.$summary['subject_line'],
            'Fecha de informe: '.$summary['date'].'. No. de informe: '.$summary['report_number'],
            'Responsable: '.$summary['teacher']['name'].' ('.$summary['teacher']['email'].'), Docente',
            'Dirigido a: '.($summary['coordinator']['name'] ?? '—').', Coordinador/a de Carrera',
            'Facultad: '.($summary['faculty'] ?? ''),
            'Carrera: '.$summary['career'],
            'Objetivo del informe: '.$summary['objective'],
            '',
            '1. ANTECEDENTES',
            $summary['background'],
            'Horario de tutorías:',
        ];
        foreach ($summary['schedule'] as $slot) {
            $day = self::DAY_LABELS[$slot['day']] ?? $slot['day'];
            $lines[] = "• {$slot['subject']}: {$day} {$slot['start']}–{$slot['end']}";
        }

        $lines[] = '';
        $lines[] = '2. RESULTADOS DE EJECUCIÓN DE LAS TUTORÍAS ACADÉMICAS';
        foreach ($summary['matrix'] as $row) {
            $lines[] = "• {$row['subject']} (ciclo ".($row['cycle'] ?? $row['cycle_name'] ?? '—')."): {$number($row['weekly_hours'])} h/semana; {$number($row['hours_done'])} h realizadas; "
                ."{$row['enrolled']} matriculados; {$row['at_risk']} con riesgo; {$row['at_risk_attending']} con riesgo asisten; "
                ."{$row['at_risk_attending_passed']} asistieron y aprobaron; {$row['at_risk_improved']} mejoraron; efectividad "
                .($row['effectiveness'] === null ? '—' : $number($row['effectiveness']).'%').'. '.($row['observation'] ?? '');
        }

        $lines[] = '';
        $lines[] = '4. CONCLUSIONES: '.($summary['conclusions'] ?? '—');
        $lines[] = '5. RECOMENDACIONES: '.($summary['recommendations'] ?? '—');

        return implode("\n", $lines);
    }
}

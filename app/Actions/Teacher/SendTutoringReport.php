<?php

namespace App\Actions\Teacher;

use App\Models\Actividad;
use App\Models\AsignaturaTutoria;
use App\Models\Asistencia;
use App\Models\InscripcionTutoria;
use App\Models\Nota;
use App\Models\Reporte;
use App\Models\TutoringSession;
use App\Models\Usuario;
use App\Support\TeacherWorkspace;
use Illuminate\Database\Eloquent\Builder;

class SendTutoringReport
{
    public function __construct(private TeacherWorkspace $workspace) {}

    /** @param array<string, mixed> $data */
    public function execute(Usuario $teacher, AsignaturaTutoria $tutoring, array $data): Reporte
    {
        return $this->workspace->write($teacher, $tutoring, function () use ($teacher, $tutoring, $data): Reporte {
            $enrollments = $tutoring->inscripciones()->with('knowledgeMetric')->get();
            $attendance = Asistencia::query()->whereHas('inscripcion', fn (Builder $query) => $query->where('fk_asig_tutoria', $tutoring->getKey()));
            $grades = Nota::query()->whereHas('inscripcion', fn (Builder $query) => $query->where('fk_asig_tutoria', $tutoring->getKey()));
            $sessions = TutoringSession::query()
                ->where('tutoring_id', $tutoring->getKey())
                ->with('attendance')
                ->orderBy('date')
                ->get();
            $allActivities = Actividad::query()
                ->whereHas('tema', fn (Builder $query) => $query->where('fk_asig_tutoria', $tutoring->getKey()))
                ->with(['tema', 'metodologias'])
                ->orderBy('id_actividad')
                ->get();

            $sessionActivitiesLines = [];
            $sessionActivitiesSummary = [];

            if ($sessions->isNotEmpty()) {
                $sessionActivitiesLines[] = '';
                $sessionActivitiesLines[] = '--- RELACIÓN DE ASISTENCIA Y ACTIVIDADES POR FECHA ---';
                foreach ($sessions as $session) {
                    $sDate = $session->date instanceof \DateTimeInterface ? $session->date->format('Y-m-d') : (string) $session->date;
                    $present = $session->attendance->where('estado_asistencia', true)->count();
                    $absent = $session->attendance->where('estado_asistencia', false)->count();
                    $total = $session->attendance->count();

                    $matchingActivities = $allActivities->filter(function (Actividad $act) use ($sDate) {
                        return $act->created_at?->toDateString() === $sDate || $act->updated_at?->toDateString() === $sDate;
                    })->values();

                    $sessionActivitiesSummary[] = [
                        'session_id' => $session->getKey(),
                        'date' => $sDate,
                        'present_count' => $present,
                        'absent_count' => $absent,
                        'total_count' => $total,
                        'activities' => $matchingActivities->map(fn (Actividad $act) => [
                            'id' => $act->getKey(),
                            'name' => $act->nombre,
                            'duration' => $act->duracion,
                            'topic' => $act->tema->nombre ?? 'Sin tema',
                            'methodologies' => $act->metodologias->pluck('descripcion')->all(),
                        ])->all(),
                    ];

                    $sessionActivitiesLines[] = "• Sesión del {$sDate}: {$present} de {$total} presentes ({$absent} ausentes).";
                    if ($matchingActivities->isNotEmpty()) {
                        $sessionActivitiesLines[] = '  Actividades de contenido en esta fecha:';
                        foreach ($matchingActivities as $act) {
                            $topicName = $act->tema ? " [Tema: {$act->tema->nombre}]" : '';
                            $sessionActivitiesLines[] = "    - {$act->nombre} (Duración: {$act->duracion}){$topicName}";
                            if ($act->metodologias->isNotEmpty()) {
                                $metNames = $act->metodologias->pluck('descripcion')->implode(', ');
                                $sessionActivitiesLines[] = "      Metodologías: {$metNames}";
                            }
                        }
                    } else {
                        $sessionActivitiesLines[] = '  Actividades: Sin actividades registradas en esta fecha.';
                    }
                }
            }

            $summary = [
                'as_of' => today()->toDateString(),
                'enrollment_count' => $enrollments->count(),
                'active_enrollment_count' => $enrollments->where('estado', true)->count(),
                'session_count' => $sessions->count(),
                'present_count' => (clone $attendance)->where('estado_asistencia', true)->count(),
                'absent_count' => (clone $attendance)->where('estado_asistencia', false)->count(),
                'topic_count' => $tutoring->temas()->count(),
                'covered_topic_count' => $tutoring->temas()->where('visto', true)->count(),
                'diagnostic_count' => (clone $grades)->where('tipo', 'diagnostic')->distinct()->count('fk_inscripcion'),
                'partial_count' => (clone $grades)->where('tipo', 'partial')->distinct()->count('fk_inscripcion'),
                'second_partial_count' => (clone $grades)->where('tipo', 'partial_two')->distinct()->count('fk_inscripcion'),
                'knowledge_groups' => $enrollments->filter(fn (InscripcionTutoria $enrollment) => $enrollment->knowledgeMetric !== null)
                    ->groupBy(fn (InscripcionTutoria $enrollment) => $enrollment->knowledgeMetric->descripcion)
                    ->map->count()->all(),
                'session_activities' => $sessionActivitiesSummary,
            ];
            $observations = trim($data['observations'] ?? '');
            $contentLines = [
                "Tutoría: {$tutoring->nombre}", "Período: {$tutoring->periodo->nombre}", "Docente: {$teacher->nombre}",
                'Fecha: '.$summary['as_of'],
                'Estudiantes inscritos: '.$summary['enrollment_count'].' (activos: '.$summary['active_enrollment_count'].')',
                'Sesiones registradas: '.$summary['session_count'],
                'Asistencias: '.$summary['present_count'].' presentes; '.$summary['absent_count'].' ausentes',
                'Temas vistos: '.$summary['covered_topic_count'].' de '.$summary['topic_count'],
                'Estudiantes con diagnóstico: '.$summary['diagnostic_count'].'; con parcial 1: '.$summary['partial_count'].'; con parcial 2: '.$summary['second_partial_count'],
                'Grupos de conocimiento: '.collect($summary['knowledge_groups'])->map(fn ($count, $name) => "{$name}: {$count}")->implode(', '),
            ];

            if (! empty($sessionActivitiesLines)) {
                $contentLines = array_merge($contentLines, $sessionActivitiesLines);
            }

            $contentLines[] = '';
            $contentLines[] = 'Observaciones: '.($observations !== '' ? $observations : 'Sin observaciones.');

            $content = implode("\n", $contentLines);
            $previous = $tutoring->reportes()->where('fk_id_usuario', $teacher->getKey())->where('tipo_reporte', $data['title'])->orderByDesc('id_reporte')->first();
            if ($previous && $previous->content === $content && $previous->summary === $summary) {
                return $previous->load('generadoPor');
            }

            return Reporte::query()->create([
                'fk_asig_tutoria' => $tutoring->getKey(), 'fk_id_usuario' => $teacher->getKey(),
                'tipo_reporte' => $data['title'], 'fecha_generacion' => now(), 'content' => $content, 'summary' => $summary,
            ])->load('generadoPor');
        });
    }
}

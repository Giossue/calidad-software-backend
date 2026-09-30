<?php

namespace App\Actions\Teacher;

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
            $summary = [
                'as_of' => today()->toDateString(),
                'enrollment_count' => $enrollments->count(),
                'active_enrollment_count' => $enrollments->where('estado', true)->count(),
                'session_count' => TutoringSession::query()->where('tutoring_id', $tutoring->getKey())->count(),
                'present_count' => (clone $attendance)->where('estado_asistencia', true)->count(),
                'absent_count' => (clone $attendance)->where('estado_asistencia', false)->count(),
                'topic_count' => $tutoring->temas()->count(),
                'covered_topic_count' => $tutoring->temas()->where('visto', true)->count(),
                'diagnostic_count' => (clone $grades)->where('tipo', 'diagnostic')->distinct()->count('fk_inscripcion'),
                'partial_count' => (clone $grades)->where('tipo', 'partial')->distinct()->count('fk_inscripcion'),
                'knowledge_groups' => $enrollments->filter(fn (InscripcionTutoria $enrollment) => $enrollment->knowledgeMetric !== null)
                    ->groupBy(fn (InscripcionTutoria $enrollment) => $enrollment->knowledgeMetric->descripcion)
                    ->map->count()->all(),
            ];
            $observations = trim($data['observations'] ?? '');
            $content = implode("\n", [
                "Tutoría: {$tutoring->nombre}", "Período: {$tutoring->periodo->nombre}", "Docente: {$teacher->nombre}",
                'Fecha: '.$summary['as_of'],
                'Estudiantes inscritos: '.$summary['enrollment_count'].' (activos: '.$summary['active_enrollment_count'].')',
                'Sesiones registradas: '.$summary['session_count'],
                'Asistencias: '.$summary['present_count'].' presentes; '.$summary['absent_count'].' ausentes',
                'Temas vistos: '.$summary['covered_topic_count'].' de '.$summary['topic_count'],
                'Estudiantes con diagnóstico: '.$summary['diagnostic_count'].'; con parcial: '.$summary['partial_count'],
                'Grupos de conocimiento: '.collect($summary['knowledge_groups'])->map(fn ($count, $name) => "{$name}: {$count}")->implode(', '),
                '', 'Observaciones: '.($observations !== '' ? $observations : 'Sin observaciones.'),
            ]);
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

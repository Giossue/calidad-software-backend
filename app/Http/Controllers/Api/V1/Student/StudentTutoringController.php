<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StudentTutoringAttendanceResource;
use App\Http\Resources\Api\V1\StudentTutoringGradeResource;
use App\Http\Resources\Api\V1\StudentTutoringResource;
use App\Http\Resources\Api\V1\StudentTutoringTopicResource;
use App\Models\AsignaturaTutoria;
use App\Models\InscripcionTutoria;
use App\Models\Tema;
use App\Models\TutoringSession;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class StudentTutoringController extends Controller
{
    /**
     * Muestra el listado de tutorías en las que el estudiante autenticado está registrado,
     * permitiendo conocer en qué asignaturas tiene tutoría asignada, junto con su docente y horarios.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var Usuario|null $user */
        $user = $request->user();

        if (! $user || (! $user->hasRole('estudiante') && ! $user->hasRole('administrador'))) {
            abort(Response::HTTP_FORBIDDEN, 'Solo los estudiantes pueden consultar sus tutorías asignadas.');
        }

        $enrollments = InscripcionTutoria::query()
            ->with([
                'asignaturaTutoria.periodo',
                'asignaturaTutoria.paralelo',
                'asignaturaTutoria.modalidad',
                'asignaturaTutoria.ciclo',
                'asignaturaTutoria.docente',
                'asignaturaTutoria.horarios',
            ])
            ->where('fk_id_usuario', $user->getKey())
            ->orderByDesc('fecha_inscripcion')
            ->orderByDesc('id_inscripcion')
            ->get();

        return StudentTutoringResource::collection($enrollments);
    }

    /**
     * Muestra el resumen de calificaciones y diagnóstico de todas las tutorías del estudiante autenticado.
     */
    public function allGrades(Request $request): AnonymousResourceCollection
    {
        /** @var Usuario|null $user */
        $user = $request->user();

        if (! $user || (! $user->hasRole('estudiante') && ! $user->hasRole('administrador'))) {
            abort(Response::HTTP_FORBIDDEN, 'Solo los estudiantes pueden consultar sus calificaciones de tutoría.');
        }

        $enrollments = InscripcionTutoria::query()
            ->with([
                'asignaturaTutoria.periodo',
                'asignaturaTutoria.paralelo',
                'asignaturaTutoria.modalidad',
                'asignaturaTutoria.ciclo',
                'asignaturaTutoria.docente',
                'notas',
                'knowledgeMetric',
            ])
            ->where('fk_id_usuario', $user->getKey())
            ->orderByDesc('fecha_inscripcion')
            ->orderByDesc('id_inscripcion')
            ->get();

        return StudentTutoringGradeResource::collection($enrollments);
    }

    /**
     * Muestra las calificaciones, diagnóstico y escala evaluativa de una tutoría específica del estudiante autenticado.
     */
    public function grades(Request $request, AsignaturaTutoria $tutoring): StudentTutoringGradeResource
    {
        /** @var Usuario|null $user */
        $user = $request->user();

        if (! $user || (! $user->hasRole('estudiante') && ! $user->hasRole('administrador'))) {
            abort(Response::HTTP_FORBIDDEN, 'Solo los estudiantes pueden consultar sus calificaciones de tutoría.');
        }

        $enrollment = InscripcionTutoria::query()
            ->with([
                'asignaturaTutoria.periodo',
                'asignaturaTutoria.paralelo',
                'asignaturaTutoria.modalidad',
                'asignaturaTutoria.ciclo',
                'asignaturaTutoria.docente',
                'notas',
                'knowledgeMetric',
            ])
            ->where('fk_id_usuario', $user->getKey())
            ->where('fk_asig_tutoria', $tutoring->getKey())
            ->first();

        if (! $enrollment) {
            abort(Response::HTTP_FORBIDDEN, 'No estás inscrito en esta asignatura de tutoría.');
        }

        return StudentTutoringGradeResource::make($enrollment);
    }

    /**
     * Muestra el resumen y estadísticas de asistencia de todas las tutorías del estudiante autenticado.
     */
    public function allAttendance(Request $request): AnonymousResourceCollection
    {
        /** @var Usuario|null $user */
        $user = $request->user();

        if (! $user || (! $user->hasRole('estudiante') && ! $user->hasRole('administrador'))) {
            abort(Response::HTTP_FORBIDDEN, 'Solo los estudiantes pueden consultar sus asistencias de tutoría.');
        }

        $enrollments = InscripcionTutoria::query()
            ->with([
                'asignaturaTutoria.periodo',
                'asignaturaTutoria.paralelo',
                'asignaturaTutoria.ciclo',
                'asignaturaTutoria.docente',
                'asistencias.session.topics',
            ])
            ->where('fk_id_usuario', $user->getKey())
            ->orderByDesc('fecha_inscripcion')
            ->orderByDesc('id_inscripcion')
            ->get();

        return StudentTutoringAttendanceResource::collection($enrollments);
    }

    /**
     * Muestra el récord detallado de asistencias, temas abordados y porcentaje de una tutoría específica.
     */
    public function attendance(Request $request, AsignaturaTutoria $tutoring): StudentTutoringAttendanceResource
    {
        /** @var Usuario|null $user */
        $user = $request->user();

        if (! $user || (! $user->hasRole('estudiante') && ! $user->hasRole('administrador'))) {
            abort(Response::HTTP_FORBIDDEN, 'Solo los estudiantes pueden consultar sus asistencias de tutoría.');
        }

        $enrollment = InscripcionTutoria::query()
            ->with([
                'asignaturaTutoria.periodo',
                'asignaturaTutoria.paralelo',
                'asignaturaTutoria.ciclo',
                'asignaturaTutoria.docente',
                'asistencias.session.topics',
            ])
            ->where('fk_id_usuario', $user->getKey())
            ->where('fk_asig_tutoria', $tutoring->getKey())
            ->first();

        if (! $enrollment) {
            abort(Response::HTTP_FORBIDDEN, 'No estás inscrito en esta asignatura de tutoría.');
        }

        return StudentTutoringAttendanceResource::make($enrollment);
    }

    /**
     * Muestra el plan didáctico, progreso de temas abordados, actividades y metodologías de una tutoría.
     */
    public function topics(Request $request, AsignaturaTutoria $tutoring): JsonResponse
    {
        /** @var Usuario|null $user */
        $user = $request->user();

        if (! $user || (! $user->hasRole('estudiante') && ! $user->hasRole('administrador'))) {
            abort(Response::HTTP_FORBIDDEN, 'Solo los estudiantes pueden consultar los temas de tutoría.');
        }

        $isEnrolled = InscripcionTutoria::query()
            ->where('fk_id_usuario', $user->getKey())
            ->where('fk_asig_tutoria', $tutoring->getKey())
            ->exists();

        if (! $isEnrolled) {
            abort(Response::HTTP_FORBIDDEN, 'No estás inscrito en esta asignatura de tutoría.');
        }

        $topics = $tutoring->temas()
            ->with(['actividades' => fn ($query) => $query->where('estado', true)->orderBy('id_actividad'), 'actividades.metodologias'])
            ->where('estado', true)
            ->orderBy('id_tema')
            ->get();

        $totalTopics = $topics->count();
        $coveredTopics = $topics->where('visto', true)->count();
        $pendingTopics = $totalTopics - $coveredTopics;
        $progress = $totalTopics > 0 ? round(($coveredTopics / $totalTopics) * 100, 2) : 0.0;

        $subject = $tutoring->loadMissing(['periodo', 'ciclo', 'paralelo', 'docente']);

        return response()->json([
            'data' => [
                'tutoring' => [
                    'id' => $subject->getKey(),
                    'name' => $subject->nombre,
                    'is_active' => (bool) $subject->estado,
                    'academic_period' => $subject->periodo ? [
                        'id' => $subject->periodo->getKey(),
                        'name' => $subject->periodo->nombre,
                    ] : null,
                    'section' => $subject->paralelo ? [
                        'id' => $subject->paralelo->getKey(),
                        'name' => $subject->paralelo->nombre,
                    ] : null,
                    'cycle' => $subject->ciclo ? [
                        'id' => $subject->ciclo->getKey(),
                        'name' => $subject->ciclo->nombre,
                    ] : null,
                    'teacher' => $subject->docente ? [
                        'id' => $subject->docente->getKey(),
                        'name' => $subject->docente->nombre,
                        'email' => $subject->docente->correo,
                    ] : null,
                ],
                'progress' => [
                    'total_topics' => $totalTopics,
                    'covered_topics' => $coveredTopics,
                    'pending_topics' => $pendingTopics,
                    'progress_percentage' => $progress,
                ],
                'topics' => StudentTutoringTopicResource::collection($topics),
                'sessions' => TutoringSession::query()
                    ->where('tutoring_id', $tutoring->getKey())
                    ->with([
                        'topics' => fn ($q) => $q->where('estado', true),
                        'topics.actividades' => fn ($q) => $q->where('estado', true)->orderBy('id_actividad'),
                        'topics.actividades.metodologias' => fn ($q) => $q->where('estado', true),
                    ])
                    ->orderByDesc('date')
                    ->get()
                    ->map(function (TutoringSession $session) use ($tutoring) {
                        $sessionTopics = $session->topics;
                        if ($sessionTopics->isEmpty() && $session->topics_covered) {
                            $sessionDate = $session->date->toDateString();
                            $sessionTopics = $tutoring->temas()
                                ->where('estado', true)
                                ->where(function ($q) use ($sessionDate) {
                                    $q->whereDate('created_at', $sessionDate)
                                        ->orWhereDate('updated_at', $sessionDate)
                                        ->orWhere('visto', true);
                                })
                                ->with([
                                    'actividades' => fn ($q) => $q->where('estado', true)->orderBy('id_actividad'),
                                    'actividades.metodologias' => fn ($q) => $q->where('estado', true),
                                ])
                                ->get();
                        }

                        return [
                            'id' => $session->getKey(),
                            'tutoring_id' => $session->tutoring_id,
                            'date' => $session->date->toDateString(),
                            'topics_covered' => (bool) $session->topics_covered,
                            'topics' => StudentTutoringTopicResource::collection($sessionTopics),
                        ];
                    }),
            ],
        ]);
    }

    /**
     * Muestra las sesiones de clase de una tutoría, permitiendo ver los temas y actividades abordados por fecha.
     */
    public function sessions(Request $request, AsignaturaTutoria $tutoring): JsonResponse
    {
        /** @var Usuario|null $user */
        $user = $request->user();

        if (! $user || (! $user->hasRole('estudiante') && ! $user->hasRole('administrador'))) {
            abort(Response::HTTP_FORBIDDEN, 'Solo los estudiantes pueden consultar las sesiones de tutoría.');
        }

        $isEnrolled = InscripcionTutoria::query()
            ->where('fk_id_usuario', $user->getKey())
            ->where('fk_asig_tutoria', $tutoring->getKey())
            ->exists();

        if (! $isEnrolled) {
            abort(Response::HTTP_FORBIDDEN, 'No estás inscrito en esta asignatura de tutoría.');
        }

        $sessions = TutoringSession::query()
            ->where('tutoring_id', $tutoring->getKey())
            ->with([
                'topics' => fn ($q) => $q->where('estado', true),
                'topics.actividades' => fn ($q) => $q->where('estado', true)->orderBy('id_actividad'),
                'topics.actividades.metodologias' => fn ($q) => $q->where('estado', true),
            ])
            ->orderByDesc('date')
            ->get();

        return response()->json([
            'data' => $sessions->map(function (TutoringSession $session) use ($tutoring) {
                $sessionTopics = $session->topics;
                if ($sessionTopics->isEmpty() && $session->topics_covered) {
                    $sessionDate = $session->date->toDateString();
                    $sessionTopics = $tutoring->temas()
                        ->where('estado', true)
                        ->where(function ($q) use ($sessionDate) {
                            $q->whereDate('created_at', $sessionDate)
                                ->orWhereDate('updated_at', $sessionDate)
                                ->orWhere('visto', true);
                        })
                        ->with([
                            'actividades' => fn ($q) => $q->where('estado', true)->orderBy('id_actividad'),
                            'actividades.metodologias' => fn ($q) => $q->where('estado', true),
                        ])
                        ->get();
                }

                return [
                    'id' => $session->getKey(),
                    'tutoring_id' => $session->tutoring_id,
                    'date' => $session->date->toDateString(),
                    'topics_covered' => (bool) $session->topics_covered,
                    'topics' => StudentTutoringTopicResource::collection($sessionTopics),
                ];
            }),
        ]);
    }

    /**
     * Muestra el detalle específico de un tema con sus actividades y metodologías.
     */
    public function topicDetail(Request $request, AsignaturaTutoria $tutoring, Tema $topic): StudentTutoringTopicResource
    {
        /** @var Usuario|null $user */
        $user = $request->user();

        if (! $user || (! $user->hasRole('estudiante') && ! $user->hasRole('administrador'))) {
            abort(Response::HTTP_FORBIDDEN, 'Solo los estudiantes pueden consultar los temas de tutoría.');
        }

        $isEnrolled = InscripcionTutoria::query()
            ->where('fk_id_usuario', $user->getKey())
            ->where('fk_asig_tutoria', $tutoring->getKey())
            ->exists();

        if (! $isEnrolled) {
            abort(Response::HTTP_FORBIDDEN, 'No estás inscrito en esta asignatura de tutoría.');
        }

        if ($topic->fk_asig_tutoria !== $tutoring->getKey()) {
            abort(Response::HTTP_NOT_FOUND, 'El tema no pertenece a la tutoría especificada.');
        }

        return StudentTutoringTopicResource::make(
            $topic->load(['actividades' => fn ($query) => $query->where('estado', true), 'actividades.metodologias'])
        );
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StudentTutoringAttendanceResource;
use App\Http\Resources\Api\V1\StudentTutoringGradeResource;
use App\Http\Resources\Api\V1\StudentTutoringResource;
use App\Models\AsignaturaTutoria;
use App\Models\InscripcionTutoria;
use App\Models\Usuario;
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
}

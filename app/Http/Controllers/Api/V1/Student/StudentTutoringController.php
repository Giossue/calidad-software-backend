<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StudentTutoringResource;
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
}

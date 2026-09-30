<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Actions\Student\SubmitDegreeTopic;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Student\StoreStudentDegreeTopicRequest;
use App\Http\Resources\Api\V1\DegreeTopicResource;
use App\Models\TemaTitulacion;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class StudentDegreeTopicController extends Controller
{
    /**
     * Muestra las propuestas de tema de titulación del estudiante autenticado,
     * incluyendo el estado de revisión y las observaciones registradas por coordinación.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var Usuario|null $user */
        $user = $request->user();

        if (! $user || (! $user->hasRole('estudiante') && ! $user->hasRole('administrador'))) {
            abort(Response::HTTP_FORBIDDEN, 'Solo los estudiantes pueden consultar sus propuestas de titulación.');
        }

        $topics = TemaTitulacion::query()
            ->with([
                'estudiante.paralelos',
                'periodo',
                'coordinadorRevisor',
                'asignaciones.docente',
                'observaciones.coordinador',
            ])
            ->where('fk_id_usuario', $user->getKey())
            ->orderByDesc('fecha_propuesta')
            ->orderByDesc('id_tema_tit')
            ->get();

        return DegreeTopicResource::collection($topics);
    }

    /**
     * Registra una nueva propuesta de tema de titulación para el estudiante autenticado,
     * iniciando el proceso de revisión y aprobación con la Coordinación de Titulación.
     */
    public function store(
        StoreStudentDegreeTopicRequest $request,
        SubmitDegreeTopic $submitDegreeTopic,
    ): JsonResponse {
        /** @var Usuario $user */
        $user = $request->user();

        $topic = $submitDegreeTopic->handle(
            student: $user,
            title: $request->resolvedTitle(),
            description: $request->resolvedDescription(),
            periodId: $request->resolvedPeriodId(),
            sectionId: $request->filled('section_id') ? $request->integer('section_id') : null,
        );

        return (new DegreeTopicResource($topic->load([
            'estudiante.paralelos',
            'periodo',
            'coordinadorRevisor',
            'asignaciones.docente',
            'observaciones.coordinador',
        ])))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Actions\Student\SubmitDegreeTopic;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Student\StoreStudentDegreeTopicRequest;
use App\Http\Requests\Api\V1\Student\UpdateStudentDegreeTopicRequest;
use App\Http\Resources\Api\V1\DegreeTopicResource;
use App\Http\Resources\Api\V1\StudentDegreeAssignmentResource;
use App\Models\MatriculaTitulacion;
use App\Models\PeriodoAcademico;
use App\Models\TemaTitulacion;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class StudentDegreeTopicController extends Controller
{
    /**
     * Muestra las propuestas de tema de titulación del estudiante autenticado,
     * incluyendo el estado de revisión y las observaciones registradas por coordinación.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var Usuario $user */
        $user = $request->user();

        Gate::authorize('viewOwn', TemaTitulacion::class);

        $topics = TemaTitulacion::query()
            ->with(TemaTitulacion::REVIEW_RELATIONS)
            ->where('fk_id_usuario', $user->getKey())
            ->withoutSupersededRejections()
            ->orderByDesc('fecha_propuesta')
            ->orderByDesc('id_tema_tit')
            ->get();

        return DegreeTopicResource::collection($topics);
    }

    /**
     * Muestra el detalle de una propuesta específica perteneciente al estudiante.
     */
    public function show(Request $request, TemaTitulacion $topic): JsonResponse
    {
        /** @var Usuario|null $user */
        $user = $request->user();

        if (! $user || (! $user->hasRole('estudiante') && ! $user->hasRole('administrador'))) {
            abort(Response::HTTP_FORBIDDEN, 'Solo los estudiantes pueden consultar sus propuestas de titulación.');
        }

        if (! $user->hasRole('administrador') && (int) $topic->fk_id_usuario !== (int) $user->getKey()) {
            abort(Response::HTTP_FORBIDDEN, 'No tienes permiso para consultar este tema de titulación.');
        }

        return (new DegreeTopicResource($topic->load(TemaTitulacion::REVIEW_RELATIONS)))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Registra una nueva propuesta de tema de titulación para el estudiante autenticado,
     * garantizando que quede automáticamente asignado a un paralelo e iniciando el
     * proceso de revisión y aprobación con la Coordinación de Titulación.
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
            replacePending: $request->wantsToReplacePending(),
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

    /**
     * Modifica una propuesta de titulación pendiente para el estudiante ("cambiarla").
     */
    public function update(
        UpdateStudentDegreeTopicRequest $request,
        TemaTitulacion $topic,
        SubmitDegreeTopic $submitDegreeTopic,
    ): JsonResponse {
        $updatedTopic = $submitDegreeTopic->update(
            topic: $topic,
            title: $request->resolvedTitle(),
            description: $request->resolvedDescription(),
            sectionId: $request->filled('section_id') ? $request->integer('section_id') : null,
        );

        return (new DegreeTopicResource($updatedTopic->load([
            'estudiante.paralelos',
            'periodo',
            'coordinadorRevisor',
            'asignaciones.docente',
            'observaciones.coordinador',
        ])))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Consulta el docente tutor y pares académicos asignados al proceso de titulación del estudiante.
     * Solo lectura; disponible tras la aprobación del tema.
     */
    public function assignments(Request $request): JsonResponse
    {
        /** @var Usuario|null $user */
        $user = $request->user();

        if (! $user || (! $user->hasRole('estudiante') && ! $user->hasRole('administrador'))) {
            abort(Response::HTTP_FORBIDDEN, 'Solo los estudiantes pueden consultar su tutor y pares asignados.');
        }

        $query = TemaTitulacion::query()
            ->with([
                'estudiante.paralelos',
                'periodo',
                'coordinadorRevisor',
                'asignaciones.docente',
            ])
            ->where('fk_id_usuario', $user->getKey());

        if ($request->filled('topic_id')) {
            $topic = $query->find($request->integer('topic_id'));

            if (! $topic) {
                return response()->json([
                    'message' => 'No se encontró la propuesta de titulación solicitada.',
                    'code' => 'topic_not_found',
                ], Response::HTTP_NOT_FOUND);
            }
        } else {
            $topic = $query->where('estado', 'aprobado')->first();

            if (! $topic) {
                $anyTopic = TemaTitulacion::query()->where('fk_id_usuario', $user->getKey())->first();

                if ($anyTopic) {
                    return response()->json([
                        'message' => 'La asignación de tutor y pares académicos solo está disponible tras la aprobación del tema de titulación.',
                        'code' => 'topic_not_approved',
                        'topic_status' => $anyTopic->estado,
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                return response()->json([
                    'message' => 'No cuentas con un tema de titulación registrado.',
                    'code' => 'no_topic',
                ], Response::HTTP_NOT_FOUND);
            }
        }

        if ($topic->estado !== 'aprobado') {
            return response()->json([
                'message' => 'La asignación de tutor y pares académicos solo está disponible tras la aprobación del tema de titulación.',
                'code' => 'topic_not_approved',
                'topic_status' => $topic->estado,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return (new StudentDegreeAssignmentResource($topic))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Consulta el tutor y pares asignados a un tema específico del estudiante tras su aprobación.
     */
    public function topicAssignments(Request $request, TemaTitulacion $topic): JsonResponse
    {
        /** @var Usuario|null $user */
        $user = $request->user();

        if (! $user || (! $user->hasRole('estudiante') && ! $user->hasRole('administrador'))) {
            abort(Response::HTTP_FORBIDDEN, 'Solo los estudiantes pueden consultar su tutor y pares asignados.');
        }

        if (! $user->hasRole('administrador') && (int) $topic->fk_id_usuario !== (int) $user->getKey()) {
            abort(Response::HTTP_FORBIDDEN, 'No tienes permiso para consultar las asignaciones de este tema de titulación.');
        }

        if ($topic->estado !== 'aprobado') {
            return response()->json([
                'message' => 'La asignación de tutor y pares académicos solo está disponible tras la aprobación del tema de titulación.',
                'code' => 'topic_not_approved',
                'topic_status' => $topic->estado,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return (new StudentDegreeAssignmentResource($topic->load([
            'estudiante.paralelos',
            'periodo',
            'coordinadorRevisor',
            'asignaciones.docente',
        ])))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Consulta si el estudiante autenticado está matriculado en titulación para el período vigente.
     */
    public function enrollmentStatus(Request $request): JsonResponse
    {
        /** @var Usuario|null $user */
        $user = $request->user();

        if (! $user || (! $user->hasRole('estudiante') && ! $user->hasRole('administrador'))) {
            return response()->json([
                'is_enrolled' => false,
                'period_id' => null,
                'period_name' => null,
            ]);
        }

        $currentPeriod = PeriodoAcademico::query()->where('estado', true)->first();
        if (! $currentPeriod) {
            return response()->json([
                'is_enrolled' => false,
                'period_id' => null,
                'period_name' => null,
            ]);
        }

        $enrolled = MatriculaTitulacion::query()
            ->where('fk_estudiante', $user->getKey())
            ->where('fk_periodo', $currentPeriod->getKey())
            ->where('estado', true)
            ->exists();

        return response()->json([
            'is_enrolled' => $enrolled,
            'period_id' => $currentPeriod->getKey(),
            'period_name' => $currentPeriod->nombre,
        ]);
    }
}

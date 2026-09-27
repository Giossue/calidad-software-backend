<?php

namespace App\Http\Controllers\Api\V1\Coordination;

use App\Actions\Coordination\ApproveDegreeTopic;
use App\Actions\Coordination\RejectDegreeTopic;
use App\Actions\Coordination\StoreTopicObservation;
use App\Actions\Coordination\UpdateAcademicPeers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Coordination\ApproveDegreeTopicRequest;
use App\Http\Requests\Api\V1\Coordination\ListDegreeTopicsRequest;
use App\Http\Requests\Api\V1\Coordination\RejectDegreeTopicRequest;
use App\Http\Requests\Api\V1\Coordination\StoreTopicObservationRequest;
use App\Http\Requests\Api\V1\Coordination\UpdateAcademicPeersRequest;
use App\Http\Resources\Api\V1\AcademicPeerResource;
use App\Http\Resources\Api\V1\DegreeTopicResource;
use App\Http\Resources\Api\V1\TopicObservationResource;
use App\Models\PeriodoAcademico;
use App\Models\TemaTitulacion;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class DegreeTopicController extends Controller
{
    public function index(ListDegreeTopicsRequest $request): JsonResponse|AnonymousResourceCollection
    {
        return $this->listTopics($request);
    }

    public function indexPending(ListDegreeTopicsRequest $request): JsonResponse|AnonymousResourceCollection
    {
        return $this->listTopics($request, pendingOnly: true);
    }

    private function listTopics(ListDegreeTopicsRequest $request, bool $pendingOnly = false): JsonResponse|AnonymousResourceCollection
    {
        $currentPeriod = PeriodoAcademico::query()->where('estado', true)->first();

        if (! $currentPeriod) {
            return response()->json([
                'message' => 'No existe un período académico vigente actualmente.',
            ], Response::HTTP_NOT_FOUND);
        }

        // The legacy pending endpoint retains its default first-section filter.
        $sectionId = $request->filled('section_id')
            ? $request->integer('section_id')
            : ($pendingOnly ? $currentPeriod->paralelos()->first()?->getKey() : null);
        $status = $pendingOnly ? 'pendiente' : $request->validated('status');
        $search = trim($request->string('search')->value());

        $query = TemaTitulacion::query()
            ->with(TemaTitulacion::REVIEW_RELATIONS)
            ->where('fk_periodo', $currentPeriod->getKey());

        if ($status !== null) {
            $query->where('estado', $status);
        }

        if ($sectionId !== null) {
            $query->whereHas('estudiante.paralelos', function (Builder $query) use ($sectionId): void {
                $query->where('paralelo.id_paralelo', $sectionId);
            });
        }

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->whereLike('titulo', "%{$search}%")
                    ->orWhereLike('descripcion', "%{$search}%")
                    ->orWhereHas('estudiante', function (Builder $student) use ($search): void {
                        $student->whereLike('nombre', "%{$search}%")
                            ->orWhereLike('cedula', "%{$search}%")
                            ->orWhereLike('correo', "%{$search}%");
                    });
            });
        }

        return DegreeTopicResource::collection(
            $query->orderByDesc('fecha_propuesta')->orderBy('id_tema_tit')->get()
        )->additional([
            'meta' => [
                'academic_period' => [
                    'id' => $currentPeriod->getKey(),
                    'name' => $currentPeriod->nombre,
                ],
                'filter_section_id' => $sectionId,
                'filter_status' => $status,
            ],
        ]);
    }

    /**
     * Muestra el detalle completo de una propuesta de tema de titulación.
     */
    public function show(Request $request, TemaTitulacion $topic): JsonResponse|DegreeTopicResource
    {
        Gate::authorize('viewAny', TemaTitulacion::class);

        $topic->load(TemaTitulacion::REVIEW_RELATIONS);

        return DegreeTopicResource::make($topic);
    }

    /**
     * Aprueba la propuesta de tema de titulación asignando tutor y pares académicos.
     */
    public function approve(
        ApproveDegreeTopicRequest $request,
        TemaTitulacion $topic,
        ApproveDegreeTopic $approveDegreeTopic
    ): DegreeTopicResource {
        /** @var Usuario $coordinator */
        $coordinator = $request->user();

        $approvedTopic = $approveDegreeTopic->handle(
            $topic,
            $coordinator,
            $request->integer('tutor_id'),
            array_map('intval', (array) $request->input('peer_ids'))
        );

        return DegreeTopicResource::make($approvedTopic);
    }

    /**
     * Rechaza la propuesta de tema de titulación registrando las observaciones.
     */
    public function reject(
        RejectDegreeTopicRequest $request,
        TemaTitulacion $topic,
        RejectDegreeTopic $rejectDegreeTopic
    ): DegreeTopicResource {
        /** @var Usuario $coordinator */
        $coordinator = $request->user();

        $observation = $request->filled('observation')
            ? $request->string('observation')->value()
            : ($request->filled('reason') ? $request->string('reason')->value() : null);

        $rejectedTopic = $rejectDegreeTopic->handle(
            $topic,
            $coordinator,
            $observation
        );

        return DegreeTopicResource::make($rejectedTopic);
    }

    /**
     * Muestra los pares académicos asignados a un tema de titulación.
     */
    public function peers(Request $request, TemaTitulacion $topic): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', TemaTitulacion::class);

        $peers = $topic->asignaciones()
            ->with('docente')
            ->where('rol', 'par_academico')
            ->where('estado', true)
            ->get();

        return AcademicPeerResource::collection($peers);
    }

    /**
     * Actualiza o reasigna los pares académicos de un tema de titulación.
     */
    public function updatePeers(
        UpdateAcademicPeersRequest $request,
        TemaTitulacion $topic,
        UpdateAcademicPeers $updateAcademicPeers
    ): AnonymousResourceCollection {
        Gate::authorize('viewAny', TemaTitulacion::class);

        $peerIds = array_map('intval', (array) $request->input('peer_ids'));
        $peers = $updateAcademicPeers->handle($topic, $peerIds);

        return AcademicPeerResource::collection($peers);
    }

    /**
     * Registra una observación en texto libre asociada al tema de titulación.
     */
    public function storeObservation(
        StoreTopicObservationRequest $request,
        TemaTitulacion $topic,
        StoreTopicObservation $storeTopicObservation
    ): JsonResponse {
        Gate::authorize('viewAny', TemaTitulacion::class);

        /** @var Usuario $coordinator */
        $coordinator = $request->user();

        $observation = $storeTopicObservation->handle(
            $topic,
            $coordinator,
            $request->string('observation')->value()
        );

        return TopicObservationResource::make($observation)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Coordination;

use App\Actions\Coordination\ApproveDegreeTopic;
use App\Actions\Coordination\RejectDegreeTopic;
use App\Actions\Coordination\StoreTopicObservation;
use App\Actions\Coordination\UpdateAcademicPeers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Coordination\ApproveDegreeTopicRequest;
use App\Http\Requests\Api\V1\Coordination\RejectDegreeTopicRequest;
use App\Http\Requests\Api\V1\Coordination\StoreTopicObservationRequest;
use App\Http\Requests\Api\V1\Coordination\UpdateAcademicPeersRequest;
use App\Http\Resources\Api\V1\AcademicPeerResource;
use App\Http\Resources\Api\V1\DegreeTopicResource;
use App\Http\Resources\Api\V1\TopicObservationResource;
use App\Models\PeriodoAcademico;
use App\Models\TemaTitulacion;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class DegreeTopicController extends Controller
{
    /**
     * Muestra el listado de temas de titulación pendientes de revisión con su detalle,
     * correspondientes al período académico vigente y filtrados por el paralelo del estudiante.
     */
    public function indexPending(Request $request): JsonResponse|AnonymousResourceCollection
    {
        $this->ensureAuthorizedCoordinator($request->user());

        $currentPeriod = PeriodoAcademico::query()->where('estado', true)->first();

        if (! $currentPeriod) {
            return response()->json([
                'message' => 'No existe un período académico vigente actualmente.',
            ], Response::HTTP_NOT_FOUND);
        }

        // Si se envía section_id explícito, se usa; de lo contrario, se toma el paralelo vinculado al período vigente
        $sectionId = $request->filled('section_id')
            ? $request->integer('section_id')
            : $currentPeriod->paralelos()->first()?->getKey();

        $query = TemaTitulacion::query()
            ->with(['estudiante.paralelos', 'periodo', 'coordinadorRevisor'])
            ->where('fk_periodo', $currentPeriod->getKey())
            ->where(function ($q): void {
                $q->whereNull('fecha_revision')
                    ->orWhere('estado', 'pendiente');
            });

        if ($sectionId) {
            $query->whereHas('estudiante.paralelos', function ($q) use ($sectionId): void {
                $q->where('paralelo.id_paralelo', $sectionId);
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
            ],
        ]);
    }

    /**
     * Muestra el detalle completo de una propuesta de tema de titulación.
     */
    public function show(Request $request, TemaTitulacion $topic): JsonResponse|DegreeTopicResource
    {
        $this->ensureAuthorizedCoordinator($request->user());

        $topic->load(['estudiante.paralelos', 'periodo', 'coordinadorRevisor', 'asignaciones.docente']);

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
        $this->ensureAuthorizedCoordinator($request->user());

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
        $this->ensureAuthorizedCoordinator($request->user());

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
        $this->ensureAuthorizedCoordinator($request->user());

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

    private function ensureAuthorizedCoordinator(?Usuario $user): void
    {
        if (! $user || (! $user->hasRole('coordinador_titulacion') && ! $user->hasRole('administrador'))) {
            abort(Response::HTTP_FORBIDDEN, 'No autorizado para revisar temas de titulación.');
        }
    }
}

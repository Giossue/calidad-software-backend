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

    /**
     * Registra una actividad de avance en la ficha de seguimiento del tema.
     */
    public function storeActivity(Request $request, TemaTitulacion $topic): JsonResponse
    {
        Gate::authorize('viewAny', TemaTitulacion::class);

        $request->validate([
            'descripcion' => ['required', 'string', 'max:1000'],
            'completada' => ['nullable', 'boolean'],
            'docente_id' => ['nullable', 'integer', 'exists:usuario,id_usuario'],
        ]);

        $ficha = \App\Models\FichaSeguimiento::query()->firstOrCreate(
            ['fk_tema_tit' => $topic->getKey()],
            ['fecha_apertura' => now()->toDateString(), 'porcentaje_avance' => 0.00, 'estado' => 'en_progreso']
        );

        $activity = \App\Models\ActividadAvance::query()->create([
            'fk_ficha' => $ficha->getKey(),
            'fk_docente' => $request->integer('docente_id') ?: $topic->activeAssignments()->where('rol', 'tutor')->value('fk_id_usuario'),
            'descripcion' => $request->string('descripcion')->value(),
            'completada' => $request->boolean('completada'),
            'fecha_registro' => now()->toDateString(),
        ]);

        // Auto-recalculate progress
        $total = $ficha->actividades()->count();
        $done = $ficha->actividades()->where('completada', true)->count();
        if ($total > 0) {
            $ficha->update(['porcentaje_avance' => round(($done / $total) * 100, 2)]);
        }

        return response()->json([
            'message' => 'Actividad registrada exitosamente.',
            'data' => [
                'id' => $activity->getKey(),
                'description' => $activity->descripcion,
                'is_completed' => (bool) $activity->completada,
                'registered_at' => $activity->fecha_registro?->toDateString(),
                'progress_percentage' => (float) $ficha->fresh()->porcentaje_avance,
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Alterna o actualiza el estado de una actividad de avance y recalcula el progreso.
     */
    public function toggleActivity(Request $request, TemaTitulacion $topic, \App\Models\ActividadAvance $activity): JsonResponse
    {
        Gate::authorize('viewAny', TemaTitulacion::class);

        $ficha = $topic->fichaSeguimiento ?: \App\Models\FichaSeguimiento::query()->firstOrCreate(
            ['fk_tema_tit' => $topic->getKey()],
            ['fecha_apertura' => now()->toDateString(), 'porcentaje_avance' => 0.00, 'estado' => 'en_progreso']
        );

        $activity->update([
            'completada' => $request->has('completada') ? $request->boolean('completada') : ! $activity->completada,
        ]);

        $total = $ficha->actividades()->count();
        $done = $ficha->actividades()->where('completada', true)->count();
        $progress = $total > 0 ? round(($done / $total) * 100, 2) : 0.00;
        $ficha->update(['porcentaje_avance' => $progress]);

        return response()->json([
            'message' => 'Estado de actividad actualizado.',
            'data' => [
                'id' => $activity->getKey(),
                'is_completed' => (bool) $activity->completada,
                'progress_percentage' => (float) $ficha->fresh()->porcentaje_avance,
            ],
        ]);
    }

    /**
     * Actualiza directamente el porcentaje de avance de la ficha de seguimiento.
     */
    public function updateProgress(Request $request, TemaTitulacion $topic): JsonResponse
    {
        Gate::authorize('viewAny', TemaTitulacion::class);

        $request->validate([
            'porcentaje_avance' => ['required', 'numeric', 'min:0', 'max:100'],
            'estado' => ['nullable', 'string', 'max:50'],
        ]);

        $ficha = \App\Models\FichaSeguimiento::query()->firstOrCreate(
            ['fk_tema_tit' => $topic->getKey()],
            ['fecha_apertura' => now()->toDateString(), 'porcentaje_avance' => 0.00, 'estado' => 'en_progreso']
        );

        $ficha->update([
            'porcentaje_avance' => $request->float('porcentaje_avance'),
            'estado' => $request->input('estado', $ficha->estado),
        ]);

        return response()->json([
            'message' => 'Avance actualizado exitosamente.',
            'data' => [
                'id' => $ficha->getKey(),
                'progress_percentage' => (float) $ficha->porcentaje_avance,
                'status' => $ficha->estado,
            ],
        ]);
    }

    /**
     * Registra un informe de titulación asociado a la ficha de seguimiento.
     */
    public function storeReport(Request $request, TemaTitulacion $topic): JsonResponse
    {
        Gate::authorize('viewAny', TemaTitulacion::class);

        $request->validate([
            'observaciones_finales' => ['required', 'string', 'max:2000'],
        ]);

        $ficha = \App\Models\FichaSeguimiento::query()->firstOrCreate(
            ['fk_tema_tit' => $topic->getKey()],
            ['fecha_apertura' => now()->toDateString(), 'porcentaje_avance' => 0.00, 'estado' => 'en_progreso']
        );

        $report = \App\Models\InformeTitulacion::query()->create([
            'fk_ficha' => $ficha->getKey(),
            'fk_coord_tit' => $request->user()->getKey(),
            'fecha_generacion' => now()->toDateString(),
            'observaciones_finales' => $request->string('observaciones_finales')->value(),
            'estado' => true,
        ]);

        return response()->json([
            'message' => 'Informe de titulación generado exitosamente.',
            'data' => [
                'id' => $report->getKey(),
                'topic_id' => $topic->getKey(),
                'topic_title' => $topic->titulo,
                'student_name' => $topic->estudiante?->nombre,
                'generated_at' => $report->fecha_generacion?->toDateString(),
                'final_observations' => $report->observaciones_finales,
                'progress_percentage' => (float) $ficha->porcentaje_avance,
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Lista todos los informes de titulación generados para la coordinación.
     */
    public function reports(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', TemaTitulacion::class);

        $query = \App\Models\InformeTitulacion::query()
            ->with(['ficha.temaTitulacion.estudiante', 'ficha.temaTitulacion.periodo', 'coordinador'])
            ->where('estado', true);

        if ($search = trim((string) $request->string('search'))) {
            $like = "%{$search}%";
            $query->where(function (Builder $q) use ($like) {
                $q->whereLike('observaciones_finales', $like)
                    ->orWhereHas('ficha.temaTitulacion', fn (Builder $q2) => $q2->whereLike('titulo', $like)->orWhereHas('estudiante', fn (Builder $q3) => $q3->whereLike('nombre', $like)->orWhereLike('cedula', $like)));
            });
        }

        $reports = $query->orderByDesc('fecha_generacion')->orderByDesc('id_informe')->paginate($request->integer('per_page', 15));

        return response()->json([
            'data' => collect($reports->items())->map(function (\App\Models\InformeTitulacion $rep) {
                $topic = $rep->ficha?->temaTitulacion;

                return [
                    'id' => $rep->getKey(),
                    'topic_id' => $topic?->getKey(),
                    'topic_title' => $topic?->titulo ?? 'Tema no asignado',
                    'student_name' => $topic?->estudiante?->nombre ?? 'Estudiante no registrado',
                    'student_identification' => $topic?->estudiante?->cedula ?? '',
                    'coordinator_name' => $rep->coordinador?->nombre ?? '',
                    'generated_at' => $rep->fecha_generacion?->toDateString(),
                    'final_observations' => $rep->observaciones_finales,
                    'progress_percentage' => (float) ($rep->ficha?->porcentaje_avance ?? 0),
                    'period_name' => $topic?->periodo?->nombre ?? '',
                ];
            }),
            'meta' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
            ],
        ]);
    }
}

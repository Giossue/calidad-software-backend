<?php

namespace App\Http\Controllers\Api\V1\Tutoring;

use App\Actions\Tutoring\ManageTutoring;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Tutoring\AssignTutoringCycleRequest;
use App\Http\Requests\Api\V1\Tutoring\AssignTutoringTeacherRequest;
use App\Http\Requests\Api\V1\Tutoring\TutoringRequest;
use App\Http\Resources\Api\V1\TutoringResource;
use App\Models\AsignaturaTutoria;
use App\Models\Ciclo;
use App\Models\Usuario;
use App\Support\TutoringCoordinatorAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class TutoringController extends Controller
{
    private const RELATIONS = ['ciclo', 'periodo', 'modalidad', 'paralelo', 'docente'];

    public function index(Request $request, TutoringCoordinatorAccess $access): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', AsignaturaTutoria::class);
        $query = AsignaturaTutoria::query()->with(self::RELATIONS)
            ->whereHas('ciclo', fn (Builder $q) => $access->scopeCareer($q, $request->user()));
        if ($careerId = $request->integer('career_id')) {
            $query->whereHas('ciclo', fn (Builder $q) => $q->where('fk_carrera', $careerId));
        }
        if ($cycleId = $request->integer('cycle_id')) {
            $query->where('fk_ciclo', $cycleId);
        }
        if ($status = $request->string('status')->toString()) {
            $query->where('estado', $status === 'active');
        }
        if ($search = trim((string) $request->string('search'))) {
            $query->whereLike('nombre', "%{$search}%");
        }

        return TutoringResource::collection($query->orderByDesc('id_asig_tutoria')
            ->paginate(min(max($request->integer('per_page', 15), 1), 100)));
    }

    public function store(TutoringRequest $request, ManageTutoring $action): JsonResponse
    {
        return TutoringResource::make($action->create($request->user(), [
            'subject_id' => $request->integer('subject_id'),
            'cycle_id' => $request->integer('cycle_id'),
            'period_id' => $request->integer('period_id'),
            'modality_id' => $request->integer('modality_id'),
        ])->load(self::RELATIONS))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(TutoringRequest $request, AsignaturaTutoria $tutoring, ManageTutoring $action): TutoringResource
    {
        $changes = [];
        if ($request->exists('period_id')) {
            $changes['period_id'] = $request->integer('period_id');
        }
        if ($request->exists('modality_id')) {
            $changes['modality_id'] = $request->integer('modality_id');
        }

        return TutoringResource::make($action->update($tutoring, $changes)->load(self::RELATIONS));
    }

    public function deactivate(AsignaturaTutoria $tutoring): TutoringResource
    {
        Gate::authorize('update', $tutoring);
        $tutoring->update(['estado' => false]);

        return TutoringResource::make($tutoring->refresh()->load(self::RELATIONS));
    }

    public function assignCycle(AssignTutoringCycleRequest $request, AsignaturaTutoria $tutoring, ManageTutoring $action): TutoringResource
    {
        $cycle = Ciclo::query()->findOrFail($request->integer('cycle_id'));

        return TutoringResource::make($action->assignCycle($tutoring, $cycle)->load(self::RELATIONS));
    }

    public function assignTeacher(AssignTutoringTeacherRequest $request, AsignaturaTutoria $tutoring, ManageTutoring $action): TutoringResource
    {
        $teacher = Usuario::query()->findOrFail($request->integer('teacher_id'));

        return TutoringResource::make($action->assignTeacher($tutoring, $teacher)->load(self::RELATIONS));
    }
}

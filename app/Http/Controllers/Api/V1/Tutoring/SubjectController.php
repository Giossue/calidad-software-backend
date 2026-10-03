<?php

namespace App\Http\Controllers\Api\V1\Tutoring;

use App\Actions\Tutoring\ManageSubject;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Tutoring\SubjectRequest;
use App\Http\Resources\Api\V1\TutoringSubjectResource;
use App\Models\Ciclo;
use App\Models\Paralelo;
use App\Models\Subject;
use App\Support\TutoringCoordinatorAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class SubjectController extends Controller
{
    public function index(Request $request, TutoringCoordinatorAccess $access): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Subject::class);
        $query = $access->scopeCareer(Subject::query()->with('career', 'cycles.periodos', 'modality'), $request->user(), 'career_id');
        if ($careerId = $request->integer('career_id')) {
            $query->where('career_id', $careerId);
        }
        if ($cycleId = $request->integer('cycle_id')) {
            $query->whereHas('cycles', fn (Builder $q) => $q->where('ciclo.id_ciclo', $cycleId));
        }
        if ($status = $request->string('status')->toString()) {
            $query->where('is_active', $status === 'active');
        }
        if ($search = trim((string) $request->string('search'))) {
            $query->where(fn (Builder $q) => $q->whereLike('name', "%{$search}%")->orWhereLike('code', "%{$search}%"));
        }

        return TutoringSubjectResource::collection($query->orderBy('name')->paginate(min(max($request->integer('per_page', 15), 1), 100)));
    }

    public function store(SubjectRequest $request, ManageSubject $action): JsonResponse
    {
        return TutoringSubjectResource::make($action->create($request->user(), [
            'career_id' => $request->integer('career_id'),
            'code' => $request->filled('code') ? $request->string('code')->toString() : null,
            'name' => $request->string('name')->toString(),
            'cycle_id' => $request->integer('cycle_id') ?: null,
            'parallel_id' => $request->integer('parallel_id') ?: null,
            'parallel_ids' => $request->input('parallel_ids') ?: [],
            'new_parallel_name' => $request->string('new_parallel_name')->toString() ?: null,
            'modality_id' => $request->integer('modality_id') ?: null,
            'period_id' => $request->integer('period_id') ?: null,
        ])->load('career', 'cycles.periodos', 'modality'))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(SubjectRequest $request, Subject $subject, ManageSubject $action): TutoringSubjectResource
    {
        return TutoringSubjectResource::make($action->update($subject, $request->validated())->load('career', 'cycles.periodos', 'modality'));
    }

    public function deactivate(Subject $subject): TutoringSubjectResource
    {
        Gate::authorize('update', $subject);
        $subject->update(['is_active' => false]);

        return TutoringSubjectResource::make($subject->refresh()->load('career', 'cycles.periodos', 'modality'));
    }

    public function assignCycle(Subject $subject, Ciclo $cycle, ManageSubject $action): TutoringSubjectResource
    {
        Gate::authorize('update', $subject);

        return TutoringSubjectResource::make($action->assignCycle($subject, $cycle)->load('career', 'cycles.periodos', 'modality'));
    }

    public function unassignCycle(Subject $subject, Ciclo $cycle, ManageSubject $action): TutoringSubjectResource
    {
        Gate::authorize('update', $subject);

        return TutoringSubjectResource::make($action->unassignCycle($subject, $cycle)->load('career', 'cycles.periodos', 'modality'));
    }

    public function assignParallel(Request $request, Subject $subject, ManageSubject $action): TutoringSubjectResource
    {
        Gate::authorize('update', $subject);
        $request->validate([
            'parallel_id' => ['nullable', 'integer'],
            'new_parallel_name' => ['nullable', 'string', 'max:50'],
            'cycle_id' => ['nullable', 'integer'],
        ]);

        return TutoringSubjectResource::make($action->assignParallel($subject, [
            'parallel_id' => $request->integer('parallel_id') ?: null,
            'new_parallel_name' => $request->string('new_parallel_name')->toString() ?: null,
            'cycle_id' => $request->integer('cycle_id') ?: null,
        ])->load('career', 'cycles.periodos', 'modality'));
    }

    public function unassignParallel(Subject $subject, Paralelo $parallel, ManageSubject $action): TutoringSubjectResource
    {
        Gate::authorize('update', $subject);

        return TutoringSubjectResource::make($action->unassignParallel($subject, $parallel)->load('career', 'cycles.periodos', 'modality'));
    }
}

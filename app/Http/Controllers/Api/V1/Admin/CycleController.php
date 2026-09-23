<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Academic\ActivateCycle;
use App\Actions\Academic\CreateCycle;
use App\Actions\Academic\DeactivateCycle;
use App\Actions\Academic\UpdateCycle;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreCycleRequest;
use App\Http\Requests\Api\V1\Admin\UpdateCycleRequest;
use App\Http\Resources\Api\V1\CycleResource;
use App\Models\Ciclo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CycleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Ciclo::class);

        $query = Ciclo::query()->with('carrera');

        if ($search = trim((string) $request->string('search'))) {
            $query->where(function (Builder $inner) use ($search) {
                $inner->whereLike('nombre', "%{$search}%")
                    ->orWhereHas('carrera', fn (Builder $q) => $q->whereLike('nombre', "%{$search}%"));
            });
        }

        return CycleResource::collection(
            $query->orderBy('fk_carrera')->orderBy('numero')->paginate($request->integer('per_page', 15)),
        )->additional(['meta' => [
            'active_count' => Ciclo::query()->where('estado', true)->count(),
            'inactive_count' => Ciclo::query()->where('estado', false)->count(),
        ]]);
    }

    public function store(StoreCycleRequest $request, CreateCycle $createCycle): JsonResponse
    {
        $cycle = $createCycle->handle($request->validated());

        return CycleResource::make($cycle)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateCycleRequest $request, Ciclo $cycle, UpdateCycle $updateCycle): CycleResource
    {
        return CycleResource::make($updateCycle->handle($cycle, $request->validated()));
    }

    public function deactivate(Ciclo $cycle, DeactivateCycle $deactivateCycle): CycleResource
    {
        return CycleResource::make($deactivateCycle->handle($cycle));
    }

    public function activate(Ciclo $cycle, ActivateCycle $activateCycle): CycleResource
    {
        return CycleResource::make($activateCycle->handle($cycle));
    }
}

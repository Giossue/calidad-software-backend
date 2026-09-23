<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Academic\ActivateCareer;
use App\Actions\Academic\CreateCareer;
use App\Actions\Academic\DeactivateCareer;
use App\Actions\Academic\UpdateCareer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreCareerRequest;
use App\Http\Requests\Api\V1\Admin\UpdateCareerRequest;
use App\Http\Resources\Api\V1\CareerResource;
use App\Models\Carrera;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CareerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Carrera::class);

        $query = Carrera::query()->with('facultad');

        if ($search = trim((string) $request->string('search'))) {
            $query->where(function (Builder $inner) use ($search) {
                $inner->whereLike('nombre', "%{$search}%")
                    ->orWhereHas('facultad', fn (Builder $q) => $q->whereLike('nombre', "%{$search}%"));
            });
        }

        if ($request->boolean('all')) {
            return CareerResource::collection(
                (clone $query)->where('estado', true)->orderBy('nombre')->get(),
            );
        }

        return CareerResource::collection($query->orderBy('nombre')->paginate($request->integer('per_page', 15)))
            ->additional(['meta' => [
                'active_count' => Carrera::query()->where('estado', true)->count(),
                'inactive_count' => Carrera::query()->where('estado', false)->count(),
            ]]);
    }

    public function store(StoreCareerRequest $request, CreateCareer $createCareer): JsonResponse
    {
        $career = $createCareer->handle($request->validated());

        return CareerResource::make($career)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateCareerRequest $request, Carrera $career, UpdateCareer $updateCareer): CareerResource
    {
        return CareerResource::make($updateCareer->handle($career, $request->validated()));
    }

    public function deactivate(Carrera $career, DeactivateCareer $deactivateCareer): CareerResource
    {
        return CareerResource::make($deactivateCareer->handle($career));
    }

    public function activate(Carrera $career, ActivateCareer $activateCareer): CareerResource
    {
        return CareerResource::make($activateCareer->handle($career));
    }
}

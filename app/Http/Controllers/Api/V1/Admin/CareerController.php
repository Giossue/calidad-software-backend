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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CareerController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Carrera::class);

        return CareerResource::collection(
            Carrera::query()->with('facultad')->orderBy('nombre')->get(),
        );
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

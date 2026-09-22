<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Modalities\DeactivateModality;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Modalities\StoreModalityRequest;
use App\Http\Requests\Api\V1\Modalities\UpdateModalityRequest;
use App\Http\Resources\Api\V1\ModalityResource;
use App\Models\Modalidad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ModalityController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Modalidad::class);

        return ModalityResource::collection(Modalidad::query()->orderBy('nombre')->paginate());
    }

    public function store(StoreModalityRequest $request): JsonResponse
    {
        $modality = Modalidad::query()->create($request->validated());

        return ModalityResource::make($modality)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateModalityRequest $request, Modalidad $modality): ModalityResource
    {
        $modality->update($request->validated());

        return ModalityResource::make($modality->refresh());
    }

    public function deactivate(Modalidad $modality, DeactivateModality $deactivateModality): ModalityResource
    {
        $this->authorize('deactivate', $modality);

        return ModalityResource::make($deactivateModality->handle($modality));
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Sections\ActivateSection;
use App\Actions\Sections\DeactivateSection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Sections\StoreSectionRequest;
use App\Http\Requests\Api\V1\Sections\UpdateSectionRequest;
use App\Http\Resources\Api\V1\SectionResource;
use App\Models\Paralelo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SectionController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Paralelo::class);

        return SectionResource::collection(Paralelo::query()->orderBy('nombre')->paginate());
    }

    public function store(StoreSectionRequest $request): JsonResponse
    {
        $section = Paralelo::query()->create([...$request->validated(), 'estado' => true]);

        return SectionResource::make($section)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateSectionRequest $request, Paralelo $section): SectionResource
    {
        $section->update($request->validated());

        return SectionResource::make($section->refresh());
    }

    public function deactivate(Paralelo $section, DeactivateSection $deactivateSection): SectionResource
    {
        $this->authorize('deactivate', $section);

        return SectionResource::make($deactivateSection->handle($section));
    }

    public function activate(Paralelo $section, ActivateSection $activateSection): SectionResource
    {
        $this->authorize('activate', $section);

        return SectionResource::make($activateSection->handle($section));
    }
}

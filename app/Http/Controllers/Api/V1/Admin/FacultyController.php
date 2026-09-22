<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreFacultyRequest;
use App\Http\Requests\Api\V1\Admin\UpdateFacultyRequest;
use App\Http\Resources\Api\V1\FacultyResource;
use App\Models\Facultad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class FacultyController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Facultad::class);

        return FacultyResource::collection(
            Facultad::query()->where('estado', true)->orderBy('nombre')->get(),
        );
    }

    public function store(StoreFacultyRequest $request): JsonResponse
    {
        $faculty = Facultad::query()->create([
            'nombre' => $request->validated('name'),
        ])->refresh();

        return FacultyResource::make($faculty)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateFacultyRequest $request, Facultad $faculty): FacultyResource
    {
        Gate::authorize('update', $faculty);

        if ($request->exists('name')) {
            $faculty->update(['nombre' => $request->validated('name')]);
        }

        return FacultyResource::make($faculty->refresh());
    }

    public function deactivate(Facultad $faculty): FacultyResource
    {
        Gate::authorize('deactivate', $faculty);
        $faculty->update(['estado' => false]);

        return FacultyResource::make($faculty->refresh());
    }
}

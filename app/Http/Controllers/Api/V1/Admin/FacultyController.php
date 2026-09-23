<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreFacultyRequest;
use App\Http\Requests\Api\V1\Admin\UpdateFacultyRequest;
use App\Http\Resources\Api\V1\FacultyResource;
use App\Models\Facultad;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class FacultyController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Facultad::class);

        $query = Facultad::query()->withCount([
            'carreras',
            'carreras as active_careers_count' => fn (Builder $q) => $q->where('estado', true),
        ]);

        if ($search = trim((string) $request->string('search'))) {
            $query->whereLike('nombre', "%{$search}%");
        }

        if ($request->boolean('all')) {
            return FacultyResource::collection(
                (clone $query)->where('estado', true)->orderBy('nombre')->get(),
            );
        }

        return FacultyResource::collection($query->orderBy('nombre')->paginate($request->integer('per_page', 15)))
            ->additional(['meta' => [
                'active_count' => Facultad::query()->where('estado', true)->count(),
                'inactive_count' => Facultad::query()->where('estado', false)->count(),
            ]]);
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

        if ($faculty->carreras()->where('estado', true)->exists()) {
            throw ValidationException::withMessages([
                'faculty' => ['No puedes desactivar esta facultad porque tiene carreras activas asignadas. Desactiva primero sus carreras.'],
            ]);
        }

        $faculty->update(['estado' => false]);

        return FacultyResource::make($faculty->refresh());
    }

    public function activate(Facultad $faculty): FacultyResource
    {
        Gate::authorize('activate', $faculty);
        $faculty->update(['estado' => true]);

        return FacultyResource::make($faculty->refresh());
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Tutoring;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Tutoring\MallaRequest;
use App\Http\Resources\Api\V1\MallaResource;
use App\Models\Malla;
use App\Models\Subject;
use App\Support\Mallas;
use App\Support\TutoringCoordinatorAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Mallas de una carrera: el coordinador de la carrera las crea, renombra, marca vigente y elimina. */
class MallaController extends Controller
{
    public function __construct(private TutoringCoordinatorAccess $access) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Subject::class);
        $request->validate(['career_id' => ['nullable', 'integer']]);
        $query = $this->access->scopeCareer(Malla::query(), $request->user(), 'career_id');
        if ($careerId = $request->integer('career_id')) {
            $this->access->authorizeCareer($request->user(), $careerId);
            $query->where('career_id', $careerId);
        }

        return MallaResource::collection($this->withCounts($query)
            ->orderBy('career_id')->orderByDesc('is_current')->orderBy('name')->get());
    }

    public function store(MallaRequest $request): JsonResponse
    {
        $malla = DB::transaction(function () use ($request): Malla {
            $malla = Malla::query()->create([
                'career_id' => $request->integer('career_id'),
                'name' => $request->string('name')->trim()->toString(),
                'is_current' => false,
            ]);

            return $request->boolean('is_current') ? Mallas::makeCurrent($malla) : $malla;
        });

        return MallaResource::make($this->fresh($malla))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(MallaRequest $request, Malla $malla): MallaResource
    {
        DB::transaction(function () use ($request, $malla): void {
            if ($request->has('name')) {
                $malla->update(['name' => $request->string('name')->trim()->toString()]);
            }
            if ($request->boolean('is_current') && ! $malla->is_current) {
                Mallas::makeCurrent($malla);
            }
        });

        return MallaResource::make($this->fresh($malla));
    }

    public function destroy(Request $request, Malla $malla): Response
    {
        Gate::authorize('create', Subject::class);
        $this->access->authorizeCareer($request->user(), $malla->career_id);
        if ($malla->is_current) {
            throw ValidationException::withMessages(['malla' => 'No se puede eliminar la malla vigente.']);
        }
        if ($malla->subjects()->exists() || $malla->users()->exists() || $malla->tutorings()->exists()) {
            throw ValidationException::withMessages(['malla' => 'La malla tiene asignaturas, estudiantes o tutorías.']);
        }
        $malla->delete();

        return response()->noContent();
    }

    /**
     * @param  Builder<Malla>  $query
     * @return Builder<Malla>
     */
    private function withCounts(Builder $query): Builder
    {
        return $query->withCount(['subjects', 'students', 'tutorings']);
    }

    private function fresh(Malla $malla): Malla
    {
        return $this->withCounts(Malla::query()->whereKey($malla->getKey()))->firstOrFail();
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Tutoring;

use App\Actions\Tutoring\ManageTeacher;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Tutoring\TeacherRequest;
use App\Http\Resources\Api\V1\AvailableTutoringTeacherResource;
use App\Http\Resources\Api\V1\TutoringTeacherResource;
use App\Models\Usuario;
use App\Support\TutoringCoordinatorAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class TeacherController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewTutoringTeachers', Usuario::class);
        $query = Usuario::query()->whereHas('roles', fn (Builder $q) => $q->where('slug', 'docente'))
            ->with('teachingCareers');
        if (! $request->user()->hasRole('administrador')) {
            $careerIds = $request->user()->coordinatedCareers()->select('carrera.id_carrera');
            $query->whereHas('teachingCareers', fn (Builder $q) => $q->whereIn('carrera.id_carrera', $careerIds));
        }
        if ($careerId = $request->integer('career_id')) {
            $query->whereHas('teachingCareers', fn (Builder $q) => $q->where('carrera.id_carrera', $careerId));
        }
        if ($search = trim((string) $request->string('search'))) {
            $query->where(fn (Builder $q) => $q->whereLike('nombre', "%{$search}%")->orWhereLike('correo', "%{$search}%"));
        }

        return TutoringTeacherResource::collection($query->orderBy('nombre')->paginate(min(max($request->integer('per_page', 15), 1), 100)));
    }

    public function available(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewTutoringTeachers', Usuario::class);
        $query = Usuario::query()->where('estado', true)
            ->whereHas('roles', fn (Builder $q) => $q->where('slug', 'docente'));
        if (! $request->user()->hasRole('administrador') && ! $request->user()->coordinatedCareers()->exists()) {
            $query->whereRaw('1 = 0');
        }
        if ($search = trim((string) $request->string('search'))) {
            $query->where(fn (Builder $q) => $q->whereLike('nombre', "%{$search}%")->orWhereLike('correo', "%{$search}%"));
        }

        return AvailableTutoringTeacherResource::collection($query->orderBy('nombre')->limit(100)->get());
    }

    public function store(TeacherRequest $request, ManageTeacher $action, TutoringCoordinatorAccess $access): JsonResponse
    {
        $access->authorizeCareer($request->user(), $request->integer('career_id'));

        return TutoringTeacherResource::make($action->create([
            'career_id' => $request->integer('career_id'),
            'identification' => $request->string('identification')->toString(),
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'phone' => $request->string('phone')->toString(),
        ]))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(TeacherRequest $request, Usuario $teacher, ManageTeacher $action): TutoringTeacherResource
    {
        return TutoringTeacherResource::make($action->update($teacher, $request->validated()));
    }

    public function deactivate(Usuario $teacher, ManageTeacher $action): TutoringTeacherResource
    {
        Gate::authorize('updateTutoringTeacher', $teacher);

        return TutoringTeacherResource::make($action->deactivate($teacher));
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Tutoring;

use App\Actions\Tutoring\ManageTeacher;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Tutoring\TeacherRequest;
use App\Http\Resources\Api\V1\AvailableTutoringTeacherResource;
use App\Http\Resources\Api\V1\TutoringTeacherResource;
use App\Models\AsignaturaTutoria;
use App\Models\Carrera;
use App\Models\Usuario;
use App\Support\TutoringCoordinatorAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
        if ($status = $request->string('status')->toString()) {
            $query->where('estado', $status === 'active');
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
        if ($excludeCareerId = $request->integer('exclude_career_id')) {
            $query->whereDoesntHave('teachingCareers', fn (Builder $q) => $q->where('carrera.id_carrera', $excludeCareerId));
        }
        if ($careerId = $request->integer('career_id')) {
            $query->whereHas('teachingCareers', fn (Builder $q) => $q->where('carrera.id_carrera', $careerId));
        }
        // Cada palabra debe aparecer en el nombre o el correo, en cualquier orden ("torres ana" encuentra a "Ana Torres").
        foreach (preg_split('/\s+/', trim((string) $request->string('search')), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $term) {
            $query->where(fn (Builder $q) => $q->whereLike('nombre', "%{$term}%")->orWhereLike('correo', "%{$term}%"));
        }

        // Horarios ocupados del docente en el período vigente, para avisar de choques antes de guardar.
        $query->with(['asignaturasTutoria' => fn ($q) => $q->where('estado', true)
            ->whereHas('periodo', fn (Builder $p) => $p->where('estado', true))
            ->with(['horarios' => fn ($h) => $h->where('estado', true)])]);

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

    /** Vincula un docente ya registrado a una carrera (un docente puede pertenecer a varias). */
    public function linkCareer(Request $request, Usuario $teacher, TutoringCoordinatorAccess $access): TutoringTeacherResource
    {
        Gate::authorize('viewTutoringTeachers', Usuario::class);
        $data = $request->validate([
            'career_id' => ['required', 'integer', Rule::exists('carrera', 'id_carrera')->where('estado', true)],
        ], [], ['career_id' => 'carrera']);
        $access->authorizeCareer($request->user(), $data['career_id']);

        if (! $teacher->estado || ! $teacher->hasRole('docente')) {
            throw ValidationException::withMessages(['teacher' => 'Solo se pueden vincular docentes activos.']);
        }

        $teacher->teachingCareers()->syncWithoutDetaching([$data['career_id']]);

        return TutoringTeacherResource::make($teacher->load('teachingCareers'));
    }

    /** Quita a un docente de una carrera, salvo que aún tenga tutorías activas en ella. */
    public function unlinkCareer(Request $request, Usuario $teacher, Carrera $career, TutoringCoordinatorAccess $access): TutoringTeacherResource
    {
        Gate::authorize('viewTutoringTeachers', Usuario::class);
        $access->authorizeCareer($request->user(), $career->getKey());

        $hasActiveTutorings = AsignaturaTutoria::query()
            ->where('fk_docente', $teacher->getKey())
            ->where('estado', true)
            ->whereHas('ciclo', fn (Builder $q) => $q->where('fk_carrera', $career->getKey()))
            ->exists();

        if ($hasActiveTutorings) {
            throw ValidationException::withMessages([
                'career' => 'El docente tiene tutorías activas en esta carrera. Reasígnalas o desactívalas antes de quitarlo.',
            ]);
        }

        $teacher->teachingCareers()->detach($career->getKey());

        return TutoringTeacherResource::make($teacher->load('teachingCareers'));
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

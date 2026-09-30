<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Actions\Teacher\ManageEnrollment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Teacher\EnrollmentRequest;
use App\Http\Requests\Api\V1\Teacher\TeacherListRequest;
use App\Http\Requests\Api\V1\Teacher\TeacherMutationRequest;
use App\Http\Resources\Api\V1\Teacher\AvailableStudentResource;
use App\Http\Resources\Api\V1\Teacher\EnrollmentResource;
use App\Models\AsignaturaTutoria;
use App\Models\InscripcionTutoria;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EnrollmentController extends Controller
{
    public function index(TeacherListRequest $request, AsignaturaTutoria $tutoring): AnonymousResourceCollection
    {
        $query = $tutoring->inscripciones()->with(ManageEnrollment::RELATIONS);
        if ($request->filled('search')) {
            $search = '%'.$request->string('search').'%';
            $query->whereHas('estudiante', fn (Builder $query) => $query->whereLike('nombre', $search)->orWhereLike('cedula', $search)->orWhereLike('correo', $search));
        }
        if ($request->filled('status')) {
            $query->where('estado', $request->input('status') === 'active');
        }

        return EnrollmentResource::collection($query->orderByDesc('estado')
            ->orderBy(Usuario::query()->select('nombre')->whereColumn('usuario.id_usuario', 'inscripcion_tutoria.fk_id_usuario'))
            ->orderBy('id_inscripcion')->paginate($request->integer('per_page', 15)));
    }

    public function available(TeacherListRequest $request, AsignaturaTutoria $tutoring): AnonymousResourceCollection
    {
        $query = Usuario::query()->where('estado', true)->whereHas('roles', fn (Builder $query) => $query->where('slug', 'estudiante'))
            ->whereHas('paralelos', fn (Builder $query) => $query->where('paralelo.id_paralelo', $tutoring->fk_paralelo)->where('usuario_paralelo.estado', true))
            ->whereDoesntHave('inscripciones', fn (Builder $query) => $query->where('fk_asig_tutoria', $tutoring->getKey())->where('estado', true));
        if ($request->filled('search')) {
            $search = '%'.$request->string('search').'%';
            $query->where(fn (Builder $query) => $query->whereLike('nombre', $search)->orWhereLike('cedula', $search)->orWhereLike('correo', $search));
        }

        return AvailableStudentResource::collection($query->orderBy('nombre')->orderBy('id_usuario')->limit(100)->get());
    }

    public function store(EnrollmentRequest $request, AsignaturaTutoria $tutoring, ManageEnrollment $action): JsonResponse
    {
        return EnrollmentResource::make($action->create($request->user(), $tutoring, $request->validated()))->response()->setStatusCode(201);
    }

    public function update(EnrollmentRequest $request, AsignaturaTutoria $tutoring, InscripcionTutoria $enrollment, ManageEnrollment $action): EnrollmentResource
    {
        return EnrollmentResource::make($action->update($request->user(), $tutoring, $enrollment, $request->validated()));
    }

    public function deactivate(TeacherMutationRequest $request, AsignaturaTutoria $tutoring, InscripcionTutoria $enrollment, ManageEnrollment $action): EnrollmentResource
    {
        return EnrollmentResource::make($action->deactivate($request->user(), $tutoring, $enrollment));
    }
}

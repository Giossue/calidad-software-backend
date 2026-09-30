<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Teacher\TeacherListRequest;
use App\Http\Resources\Api\V1\Teacher\DegreeAssignmentResource;
use App\Models\AsignacionDocente;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DegreeAssignmentController extends Controller
{
    public function index(TeacherListRequest $request): AnonymousResourceCollection
    {
        $query = AsignacionDocente::query()->where('fk_id_usuario', $request->user()->getKey())->where('estado', true)
            ->with('temaTitulacion.estudiante', 'temaTitulacion.periodo');
        if ($request->filled('role')) {
            $query->where('rol', $request->input('role'));
        }
        if ($request->filled('search')) {
            $search = '%'.$request->string('search').'%';
            $query->whereHas('temaTitulacion', fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereLike('titulo', $search)
                ->orWhereHas('estudiante', fn (Builder $query) => $query->whereLike('nombre', $search))));
        }

        return DegreeAssignmentResource::collection($query->orderByDesc('fecha_asignacion')->orderByDesc('id_asignacion')->paginate($request->integer('per_page', 15)));
    }
}

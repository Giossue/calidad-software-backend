<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Teacher\TeacherListRequest;
use App\Http\Resources\Api\V1\Teacher\TeacherTutoringResource;
use App\Models\AsignaturaTutoria;
use App\Support\JointTutorings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class TutoringController extends Controller
{
    public function index(TeacherListRequest $request): AnonymousResourceCollection
    {
        $query = AsignaturaTutoria::query()->where('fk_docente', $request->user()->getKey())
            ->with('ciclo.carrera', 'periodo', 'modalidad', 'paralelo', 'docente', 'horarios')
            ->withCount(['inscripciones as active_enrollment_count' => fn ($query) => $query->where('estado', true)]);
        if ($request->filled('search')) {
            $query->whereLike('nombre', '%'.$request->string('search').'%');
        }
        if ($request->filled('status')) {
            $query->where('estado', $request->input('status') === 'active');
        }

        return TeacherTutoringResource::collection($query->orderByDesc('fk_periodo')->orderBy('nombre')->orderBy('id_asig_tutoria')->paginate($request->integer('per_page', 15)));
    }

    /** Tutorías que el docente dicta a la misma hora y de la misma asignatura (clase conjunta). */
    public function joint(TeacherListRequest $request, AsignaturaTutoria $tutoring): JsonResponse
    {
        return response()->json(['data' => JointTutorings::for($tutoring)->map(fn (array $joint): array => [
            'id' => $joint['tutoring']->getKey(),
            'name' => $joint['tutoring']->nombre,
            'career_name' => $joint['tutoring']->ciclo?->carrera?->nombre,
            'cycle_number' => $joint['tutoring']->ciclo?->numero,
            'section_name' => $joint['tutoring']->paralelo?->nombre,
            'days' => $joint['days'],
            'can_manage' => $request->user()->can('teach', $joint['tutoring']),
        ])->values()]);
    }

    public function gradeSettings(TeacherListRequest $request): JsonResource
    {
        return JsonResource::make(['minimum' => config('teaching.grade_min'), 'maximum' => config('teaching.grade_max'), 'groups' => config('teaching.groups')]);
    }
}

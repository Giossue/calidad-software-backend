<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Actions\Teacher\SaveSession;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Teacher\SessionRequest;
use App\Http\Requests\Api\V1\Teacher\TeacherListRequest;
use App\Http\Resources\Api\V1\Teacher\SessionResource;
use App\Http\Resources\Api\V1\TutoringAttendanceResource;
use App\Models\AsignaturaTutoria;
use App\Models\Asistencia;
use App\Models\TutoringSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SessionController extends Controller
{
    public function attendance(TeacherListRequest $request, AsignaturaTutoria $tutoring): AnonymousResourceCollection
    {
        $query = Asistencia::query()->whereHas('inscripcion', fn (Builder $query) => $query->where('fk_asig_tutoria', $tutoring->getKey()))
            ->with('estudiante', 'session');
        if ($request->filled('date')) {
            $query->whereDate('fecha', $request->input('date'));
        }

        return TutoringAttendanceResource::collection($query->orderByDesc('fecha')->orderBy('id_asistencia')->paginate($request->integer('per_page', 15)));
    }

    public function index(TeacherListRequest $request, AsignaturaTutoria $tutoring): AnonymousResourceCollection
    {
        $query = TutoringSession::query()->where('tutoring_id', $tutoring->getKey())->with('topics', 'attendance.estudiante');
        if ($request->filled('date')) {
            $query->whereDate('date', $request->input('date'));
        }

        return SessionResource::collection($query->orderByDesc('date')->orderByDesc('id')->paginate($request->integer('per_page', 15)));
    }

    public function store(SessionRequest $request, AsignaturaTutoria $tutoring, SaveSession $action): JsonResponse
    {
        return SessionResource::make($action->execute($request->user(), $tutoring, $request->sessionData()))->response()->setStatusCode(200);
    }
}

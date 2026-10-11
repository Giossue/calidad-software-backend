<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Actions\Teacher\SaveSession;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Teacher\SessionRequest;
use App\Http\Requests\Api\V1\Teacher\TeacherListRequest;
use App\Http\Resources\Api\V1\Teacher\SessionResource;
use App\Http\Resources\Api\V1\TutoringAttendanceResource;
use App\Models\AbsenceNotice;
use App\Models\AsignaturaTutoria;
use App\Models\Asistencia;
use App\Models\Tema;
use App\Models\TutoringSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

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

    /** Avisos de inasistencia de los estudiantes para una fecha (se muestran al tomar lista). */
    public function absenceNotices(TeacherListRequest $request, AsignaturaTutoria $tutoring): JsonResponse
    {
        $request->validate(['date' => ['required', 'date_format:Y-m-d']]);
        $notices = AbsenceNotice::query()->whereDate('date', $request->string('date')->toString())
            ->whereHas('enrollment', fn (Builder $query) => $query->where('fk_asig_tutoria', $tutoring->getKey()))
            ->with('enrollment.estudiante')->get();

        return response()->json(['data' => $notices->map(fn (AbsenceNotice $notice) => [
            'id' => $notice->getKey(),
            'enrollment_id' => $notice->enrollment_id,
            'student_name' => $notice->enrollment?->estudiante?->nombre ?? '',
            'date' => $notice->date->toDateString(),
            'reason' => $notice->reason,
        ])->all()]);
    }

    public function index(TeacherListRequest $request, AsignaturaTutoria $tutoring): AnonymousResourceCollection
    {
        $query = TutoringSession::query()->where('tutoring_id', $tutoring->getKey())->with('topics.actividades.metodologias', 'attendance.estudiante');
        if ($request->filled('date')) {
            $query->whereDate('date', $request->input('date'));
        }

        return SessionResource::collection($query->orderByDesc('date')->orderByDesc('id')->paginate($request->integer('per_page', 15)));
    }

    public function store(SessionRequest $request, AsignaturaTutoria $tutoring, SaveSession $action): JsonResponse
    {
        return SessionResource::make($action->execute($request->user(), $tutoring, $request->sessionData()))->response()->setStatusCode(200);
    }

    public function updateTopics(Request $request, AsignaturaTutoria $tutoring, TutoringSession $session): SessionResource
    {
        if ($session->tutoring_id !== $tutoring->getKey()) {
            abort(404, 'La sesión no pertenece a la tutoría.');
        }

        $validated = $request->validate([
            'topic_ids' => ['present', 'array'],
            'topic_ids.*' => ['integer', Rule::exists('tema', 'id_tema')->where('fk_asig_tutoria', $tutoring->getKey())],
        ]);

        $topicIds = $validated['topic_ids'];
        $session->topics()->sync($topicIds);
        $session->update(['topics_covered' => count($topicIds) > 0]);

        if (! empty($topicIds)) {
            Tema::query()->whereIn('id_tema', $topicIds)->update(['visto' => true]);
        }

        return SessionResource::make($session->load('topics.actividades.metodologias', 'attendance.estudiante'));
    }
}

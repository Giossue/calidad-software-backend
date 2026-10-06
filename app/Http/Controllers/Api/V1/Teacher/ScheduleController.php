<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Actions\Tutoring\ManageSchedule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Teacher\TeacherListRequest;
use App\Http\Requests\Api\V1\Teacher\TeacherMutationRequest;
use App\Http\Requests\Api\V1\Teacher\TeacherScheduleRequest;
use App\Http\Requests\Api\V1\Teacher\TeacherSyncScheduleRequest;
use App\Http\Resources\Api\V1\TutoringScheduleResource;
use App\Models\AsignaturaTutoria;
use App\Models\Horario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ScheduleController extends Controller
{
    public function index(TeacherListRequest $request, AsignaturaTutoria $tutoring): AnonymousResourceCollection
    {
        return TutoringScheduleResource::collection(
            $tutoring->horarios()->where('estado', true)->orderBy('dia_semana')->orderBy('hora_inicio')->get()
        );
    }

    public function sync(TeacherSyncScheduleRequest $request, AsignaturaTutoria $tutoring, ManageSchedule $action): AnonymousResourceCollection
    {
        $result = $action->sync($tutoring, $request->validatedSchedules());

        return TutoringScheduleResource::collection($result);
    }

    public function store(TeacherScheduleRequest $request, AsignaturaTutoria $tutoring, ManageSchedule $action): JsonResponse
    {
        $created = $action->create($tutoring, $request->scheduleData());

        return TutoringScheduleResource::make($created)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(TeacherScheduleRequest $request, AsignaturaTutoria $tutoring, Horario $schedule, ManageSchedule $action): TutoringScheduleResource
    {
        abort_unless($schedule->fk_asig_tutoria === $tutoring->getKey(), Response::HTTP_NOT_FOUND);
        $updated = $action->update($tutoring, $schedule, $request->scheduleData($schedule->room));

        return TutoringScheduleResource::make($updated);
    }

    public function deactivate(TeacherMutationRequest $request, AsignaturaTutoria $tutoring, Horario $schedule, ManageSchedule $action): TutoringScheduleResource
    {
        abort_unless($schedule->fk_asig_tutoria === $tutoring->getKey(), Response::HTTP_NOT_FOUND);

        return TutoringScheduleResource::make($action->deactivate($tutoring, $schedule));
    }
}

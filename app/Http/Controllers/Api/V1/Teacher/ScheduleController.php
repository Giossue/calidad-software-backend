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
use Illuminate\Support\Facades\DB;

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
        $result = DB::transaction(function () use ($tutoring, $request, $action) {
            $locked = AsignaturaTutoria::query()->whereKey($tutoring->getKey())->lockForUpdate()->firstOrFail();
            $existingActive = $locked->horarios()->where('estado', true)->get();
            $newSchedules = $request->validatedSchedules();
            $newDays = array_column($newSchedules, 'day');

            // Deactivate schedules for removed days
            foreach ($existingActive as $existing) {
                if (! in_array($existing->dia_semana, $newDays, true)) {
                    $existing->update(['estado' => false]);
                }
            }

            // Create or update schedules
            foreach ($newSchedules as $sched) {
                $day = $sched['day'];
                $startTime = $sched['start_time'];
                $endTime = $sched['end_time'];
                $match = $existingActive->firstWhere('dia_semana', $day);

                if ($match) {
                    $room = $sched['room'] ?? ($match->room ?: 'Por asignar');
                    $action->update($locked, $match, [
                        'day' => $day,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'room' => $room,
                    ]);
                } else {
                    $room = $sched['room'] ?? 'Por asignar';
                    $action->create($locked, [
                        'day' => $day,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'room' => $room,
                    ]);
                }
            }

            return $locked->horarios()->where('estado', true)->orderBy('dia_semana')->orderBy('hora_inicio')->get();
        }, 3);

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

    public function deactivate(TeacherMutationRequest $request, AsignaturaTutoria $tutoring, Horario $schedule): TutoringScheduleResource
    {
        abort_unless($schedule->fk_asig_tutoria === $tutoring->getKey(), Response::HTTP_NOT_FOUND);
        $schedule->update(['estado' => false]);

        return TutoringScheduleResource::make($schedule->refresh());
    }
}

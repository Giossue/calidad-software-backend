<?php

namespace App\Http\Controllers\Api\V1\Tutoring;

use App\Actions\Tutoring\ManageSchedule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Tutoring\ScheduleRequest;
use App\Http\Resources\Api\V1\TutoringScheduleResource;
use App\Models\AsignaturaTutoria;
use App\Models\Horario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ScheduleController extends Controller
{
    public function index(AsignaturaTutoria $tutoring): AnonymousResourceCollection
    {
        Gate::authorize('view', $tutoring);

        return TutoringScheduleResource::collection($tutoring->horarios()->orderBy('dia_semana')->orderBy('hora_inicio')->get());
    }

    public function store(ScheduleRequest $request, AsignaturaTutoria $tutoring, ManageSchedule $action): JsonResponse
    {
        return TutoringScheduleResource::make($action->create($tutoring, [
            'day' => $request->string('day')->toString(),
            'start_time' => $request->string('start_time')->toString(),
            'end_time' => $request->string('end_time')->toString(),
            'room' => $request->string('room')->toString(),
        ]))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(ScheduleRequest $request, AsignaturaTutoria $tutoring, Horario $schedule, ManageSchedule $action): TutoringScheduleResource
    {
        abort_unless($schedule->fk_asig_tutoria === $tutoring->getKey(), Response::HTTP_NOT_FOUND);

        return TutoringScheduleResource::make($action->update($tutoring, $schedule, $request->validated()));
    }

    public function deactivate(AsignaturaTutoria $tutoring, Horario $schedule): TutoringScheduleResource
    {
        Gate::authorize('update', $tutoring);
        abort_unless($schedule->fk_asig_tutoria === $tutoring->getKey(), Response::HTTP_NOT_FOUND);
        $schedule->update(['estado' => false]);

        return TutoringScheduleResource::make($schedule->refresh());
    }
}

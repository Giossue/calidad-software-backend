<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Actions\Teacher\ManageContent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Teacher\ActivityRequest;
use App\Http\Requests\Api\V1\Teacher\MethodologyRequest;
use App\Http\Requests\Api\V1\Teacher\TeacherListRequest;
use App\Http\Requests\Api\V1\Teacher\TeacherMutationRequest;
use App\Http\Requests\Api\V1\Teacher\TopicRequest;
use App\Http\Resources\Api\V1\Teacher\ActivityResource;
use App\Http\Resources\Api\V1\Teacher\MethodologyResource;
use App\Http\Resources\Api\V1\Teacher\TopicResource;
use App\Models\Actividad;
use App\Models\AsignaturaTutoria;
use App\Models\Metodologia;
use App\Models\Tema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContentController extends Controller
{
    public function index(TeacherListRequest $request, AsignaturaTutoria $tutoring): AnonymousResourceCollection
    {
        $query = $tutoring->temas()->with(['actividades' => fn ($query) => $query->orderBy('id_actividad'), 'actividades.metodologias']);
        if ($request->filled('search')) {
            $query->whereLike('nombre', '%'.$request->string('search').'%');
        }
        if ($request->filled('status')) {
            $query->where('estado', $request->input('status') === 'active');
        }

        return TopicResource::collection($query->orderBy('id_tema')->paginate($request->integer('per_page', 15)));
    }

    public function storeTopic(TopicRequest $request, AsignaturaTutoria $tutoring, ManageContent $action): JsonResponse
    {
        return TopicResource::make($action->topic($request->user(), $tutoring, $request->validated()))->response()->setStatusCode(201);
    }

    public function updateTopic(TopicRequest $request, AsignaturaTutoria $tutoring, Tema $topic, ManageContent $action): TopicResource
    {
        return TopicResource::make($action->topic($request->user(), $tutoring, $request->validated(), $topic));
    }

    public function deactivateTopic(TeacherMutationRequest $request, AsignaturaTutoria $tutoring, Tema $topic, ManageContent $action): TopicResource
    {
        return TopicResource::make($action->deactivate($request->user(), $tutoring, $topic)->load('actividades.metodologias'));
    }

    public function storeActivity(ActivityRequest $request, AsignaturaTutoria $tutoring, Tema $topic, ManageContent $action): JsonResponse
    {
        return ActivityResource::make($action->activity($request->user(), $tutoring, $topic, $request->validated()))->response()->setStatusCode(201);
    }

    public function updateActivity(ActivityRequest $request, AsignaturaTutoria $tutoring, Tema $topic, Actividad $activity, ManageContent $action): ActivityResource
    {
        return ActivityResource::make($action->activity($request->user(), $tutoring, $topic, $request->validated(), $activity));
    }

    public function deactivateActivity(TeacherMutationRequest $request, AsignaturaTutoria $tutoring, Tema $topic, Actividad $activity, ManageContent $action): ActivityResource
    {
        return ActivityResource::make($action->deactivate($request->user(), $tutoring, $activity)->load('metodologias'));
    }

    public function storeMethodology(MethodologyRequest $request, AsignaturaTutoria $tutoring, Tema $topic, Actividad $activity, ManageContent $action): JsonResponse
    {
        return MethodologyResource::make($action->methodology($request->user(), $tutoring, $topic, $activity, $request->validated()))->response()->setStatusCode(201);
    }

    public function updateMethodology(MethodologyRequest $request, AsignaturaTutoria $tutoring, Tema $topic, Actividad $activity, Metodologia $methodology, ManageContent $action): MethodologyResource
    {
        return MethodologyResource::make($action->methodology($request->user(), $tutoring, $topic, $activity, $request->validated(), $methodology));
    }

    public function deactivateMethodology(TeacherMutationRequest $request, AsignaturaTutoria $tutoring, Tema $topic, Actividad $activity, Metodologia $methodology, ManageContent $action): MethodologyResource
    {
        return MethodologyResource::make($action->deactivate($request->user(), $tutoring, $methodology));
    }
}

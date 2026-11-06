<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Actions\Teacher\ManageTopicFiles;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Teacher\TeacherListRequest;
use App\Http\Requests\Api\V1\Teacher\TeacherMutationRequest;
use App\Http\Requests\Api\V1\Teacher\TopicFileRequest;
use App\Http\Resources\Api\V1\TopicFileResource;
use App\Models\AsignaturaTutoria;
use App\Models\Tema;
use App\Models\TopicFile;
use App\Support\TopicFiles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TopicFileController extends Controller
{
    public function store(TopicFileRequest $request, AsignaturaTutoria $tutoring, Tema $topic, ManageTopicFiles $action): JsonResponse
    {
        return TopicFileResource::make($action->upload($request->user(), $tutoring, $topic, $request->file('file')))->response()->setStatusCode(201);
    }

    public function destroy(TeacherMutationRequest $request, AsignaturaTutoria $tutoring, Tema $topic, TopicFile $file, ManageTopicFiles $action): Response
    {
        $action->delete($request->user(), $tutoring, $topic, $file);

        return response()->noContent();
    }

    /** El docente descarga los recursos de sus tutorías, también en modo consulta. */
    public function download(TeacherListRequest $request, AsignaturaTutoria $tutoring, Tema $topic, TopicFile $file): StreamedResponse
    {
        abort_unless($topic->fk_asig_tutoria === $tutoring->getKey() && $file->topic_id === $topic->getKey(), 404);

        return self::stream($file);
    }

    public static function stream(TopicFile $file): StreamedResponse
    {
        abort_unless(TopicFiles::disk()->exists($file->path), 404, 'El archivo ya no está disponible.');

        return TopicFiles::disk()->download($file->path, $file->original_name, [
            'Content-Type' => $file->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

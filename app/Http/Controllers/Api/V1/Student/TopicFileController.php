<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Api\V1\Teacher\TopicFileController as TeacherTopicFileController;
use App\Http\Controllers\Controller;
use App\Models\AsignaturaTutoria;
use App\Models\InscripcionTutoria;
use App\Models\Tema;
use App\Models\TopicFile;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TopicFileController extends Controller
{
    /** El estudiante descarga los recursos de los temas activos de sus tutorías. */
    public function download(Request $request, AsignaturaTutoria $tutoring, Tema $topic, TopicFile $file): StreamedResponse
    {
        /** @var Usuario $user */
        $user = $request->user();
        abort_unless($user->hasRole('estudiante'), Response::HTTP_FORBIDDEN, 'Solo los estudiantes pueden descargar los recursos de sus tutorías.');

        $enrolled = InscripcionTutoria::query()
            ->where('fk_id_usuario', $user->getKey())
            ->where('fk_asig_tutoria', $tutoring->getKey())
            ->exists();
        abort_unless($enrolled, Response::HTTP_FORBIDDEN, 'No estás inscrito en esta asignatura de tutoría.');
        abort_unless($topic->fk_asig_tutoria === $tutoring->getKey() && $topic->estado && $file->topic_id === $topic->getKey(), Response::HTTP_NOT_FOUND);

        return TeacherTopicFileController::stream($file);
    }
}

<?php

namespace App\Actions\Teacher;

use App\Models\AsignaturaTutoria;
use App\Models\Tema;
use App\Models\TutoringSession;
use App\Models\Usuario;
use App\Support\TeacherWorkspace;

/**
 * Elimina una sesión registrada por error: su asistencia y su vínculo con los temas. Los temas
 * se conservan (quedan sin sesión) y su «visto» se recalcula con las sesiones que les quedan.
 */
class DeleteSession
{
    public function __construct(private TeacherWorkspace $workspace) {}

    public function execute(Usuario $teacher, AsignaturaTutoria $tutoring, TutoringSession $session): void
    {
        $this->workspace->write($teacher, $tutoring, function () use ($tutoring, $session): void {
            abort_unless($session->tutoring_id === $tutoring->getKey(), 404, 'La sesión no pertenece a la tutoría.');
            $topicIds = $session->topics()->pluck('id_tema')->all();

            $session->attendance()->delete();
            $session->topics()->detach();
            $session->delete();

            foreach (Tema::query()->whereKey($topicIds)->get() as $topic) {
                $topic->update(['visto' => $topic->sessions()->where('topics_covered', true)->exists()]);
            }
        });
    }
}

<?php

namespace App\Support;

use App\Models\Actividad;
use App\Models\AsignaturaTutoria;
use App\Models\InscripcionTutoria;
use App\Models\Metodologia;
use App\Models\Tema;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TeacherWorkspace
{
    /**
     * Lock before checking assignment, so a concurrent reassignment cannot grant
     * the previous teacher permission to finish a new write.
     *
     * @template T
     *
     * @param  callable(AsignaturaTutoria): T  $operation
     * @return T
     */
    public function write(Usuario $user, AsignaturaTutoria $tutoring, callable $operation): mixed
    {
        return DB::transaction(function () use ($user, $tutoring, $operation) {
            $locked = AsignaturaTutoria::query()->whereKey($tutoring->getKey())->lockForUpdate()->firstOrFail();
            Gate::forUser($user)->authorize('teach', $locked);

            return $operation($locked);
        });
    }

    public function enrollment(AsignaturaTutoria $tutoring, InscripcionTutoria $enrollment): void
    {
        abort_unless($enrollment->fk_asig_tutoria === $tutoring->getKey(), 404);
    }

    public function topic(AsignaturaTutoria $tutoring, Tema $topic): void
    {
        abort_unless($topic->fk_asig_tutoria === $tutoring->getKey(), 404);
    }

    public function activity(AsignaturaTutoria $tutoring, Tema $topic, Actividad $activity): void
    {
        $this->topic($tutoring, $topic);
        abort_unless($activity->fk_tema === $topic->getKey(), 404);
    }

    public function methodology(AsignaturaTutoria $tutoring, Tema $topic, Actividad $activity, Metodologia $methodology): void
    {
        $this->activity($tutoring, $topic, $activity);
        abort_unless($methodology->fk_actividad === $activity->getKey(), 404);
    }
}

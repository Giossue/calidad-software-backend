<?php

namespace App\Http\Requests\Api\V1\Teacher;

use App\Models\Actividad;
use App\Models\AsignaturaTutoria;
use App\Models\InscripcionTutoria;
use App\Models\Metodologia;
use App\Models\Tema;
use App\Support\TeacherWorkspace;
use Illuminate\Foundation\Http\FormRequest;

class TeacherMutationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tutoring = $this->route('tutoring');
        if (! $tutoring instanceof AsignaturaTutoria || ! $this->user()->can('teach', $tutoring)) {
            return false;
        }
        $workspace = app(TeacherWorkspace::class);
        $enrollment = $this->route('enrollment');
        $topic = $this->route('topic');
        $activity = $this->route('activity');
        $methodology = $this->route('methodology');
        if ($enrollment instanceof InscripcionTutoria) {
            $workspace->enrollment($tutoring, $enrollment);
        }
        if ($topic instanceof Tema) {
            $workspace->topic($tutoring, $topic);
            if ($activity instanceof Actividad) {
                $workspace->activity($tutoring, $topic, $activity);
                if ($methodology instanceof Metodologia) {
                    $workspace->methodology($tutoring, $topic, $activity, $methodology);
                }
            }
        }

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    public function selectedTutoring(): AsignaturaTutoria
    {
        $tutoring = $this->route('tutoring');
        abort_unless($tutoring instanceof AsignaturaTutoria, 404);

        return $tutoring;
    }

    public function selectedTopic(): Tema
    {
        $topic = $this->route('topic');
        abort_unless($topic instanceof Tema, 404);

        return $topic;
    }
}

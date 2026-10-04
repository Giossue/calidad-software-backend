<?php

namespace App\Actions\Teacher;

use App\Models\Actividad;
use App\Models\AsignaturaTutoria;
use App\Models\Metodologia;
use App\Models\Tema;
use App\Models\Usuario;
use App\Support\TeacherWorkspace;
use Illuminate\Validation\ValidationException;

class ManageContent
{
    public function __construct(private TeacherWorkspace $workspace) {}

    /** @param array<string, mixed> $data */
    public function topic(Usuario $teacher, AsignaturaTutoria $tutoring, array $data, ?Tema $topic = null): Tema
    {
        return $this->workspace->write($teacher, $tutoring, function () use ($tutoring, $data, $topic): Tema {
            if ($topic) {
                $this->workspace->topic($tutoring, $topic);
                $this->active($topic->estado);
            }
            if (isset($data['name']) && $tutoring->temas()->where('nombre', $data['name'])->when($topic, fn ($query) => $query->whereKeyNot($topic->getKey()))->exists()) {
                throw ValidationException::withMessages(['name' => 'Ya existe un tema con ese nombre en esta tutoría.']);
            }
            $topic ??= new Tema(['fk_asig_tutoria' => $tutoring->getKey(), 'visto' => false, 'estado' => true]);
            $attributes = [];
            if (array_key_exists('name', $data)) {
                $attributes['nombre'] = $data['name'];
            }
            if (array_key_exists('description', $data)) {
                $attributes['descripcion'] = $data['description'];
            }
            if (array_key_exists('is_covered', $data)) {
                $attributes['visto'] = (bool) $data['is_covered'];
            }
            $topic->fill($attributes)->save();

            return $topic->refresh()->load('actividades.metodologias');
        });
    }

    public function toggleCovered(Usuario $teacher, AsignaturaTutoria $tutoring, Tema $topic, ?bool $isCovered = null): Tema
    {
        return $this->workspace->write($teacher, $tutoring, function () use ($tutoring, $topic, $isCovered): Tema {
            $this->workspace->topic($tutoring, $topic);
            $this->active($topic->estado);
            $topic->update(['visto' => $isCovered ?? ! $topic->visto]);

            return $topic->refresh()->load('actividades.metodologias');
        });
    }

    /** @param array<string, mixed> $data */
    public function activity(Usuario $teacher, AsignaturaTutoria $tutoring, Tema $topic, array $data, ?Actividad $activity = null): Actividad
    {
        return $this->workspace->write($teacher, $tutoring, function () use ($tutoring, $topic, $data, $activity): Actividad {
            $this->workspace->topic($tutoring, $topic);
            $this->active($topic->estado);
            if ($activity) {
                $this->workspace->activity($tutoring, $topic, $activity);
                $this->active($activity->estado);
            }
            if ($topic->actividades()->where('nombre', $data['name'])->when($activity, fn ($query) => $query->whereKeyNot($activity->getKey()))->exists()) {
                throw ValidationException::withMessages(['name' => 'Ya existe una actividad con ese nombre en este tema.']);
            }
            $activity ??= new Actividad(['fk_tema' => $topic->getKey(), 'estado' => true]);
            $activity->fill(['nombre' => $data['name'], 'duracion' => $data['duration']])->save();

            return $activity->refresh()->load('metodologias');
        });
    }

    /** @param array<string, mixed> $data */
    public function methodology(Usuario $teacher, AsignaturaTutoria $tutoring, Tema $topic, Actividad $activity, array $data, ?Metodologia $methodology = null): Metodologia
    {
        return $this->workspace->write($teacher, $tutoring, function () use ($tutoring, $topic, $activity, $data, $methodology): Metodologia {
            $this->workspace->activity($tutoring, $topic, $activity);
            $this->active($topic->estado && $activity->estado);
            if ($methodology) {
                $this->workspace->methodology($tutoring, $topic, $activity, $methodology);
                $this->active($methodology->estado);
            }
            $methodology ??= new Metodologia(['fk_actividad' => $activity->getKey(), 'estado' => true]);
            $methodology->fill(['descripcion' => $data['description']])->save();

            return $methodology->refresh();
        });
    }

    /**
     * @template T of Tema|Actividad|Metodologia
     *
     * @param  T  $record
     * @return T
     */
    public function deactivate(Usuario $teacher, AsignaturaTutoria $tutoring, Tema|Actividad|Metodologia $record): Tema|Actividad|Metodologia
    {
        return $this->workspace->write($teacher, $tutoring, function () use ($tutoring, $record) {
            $topic = $record instanceof Tema ? $record : ($record instanceof Actividad ? $record->tema : $record->actividad->tema);
            $this->workspace->topic($tutoring, $topic);
            $record->update(['estado' => false]);

            return $record->refresh();
        });
    }

    private function active(bool $active): void
    {
        if (! $active) {
            throw ValidationException::withMessages(['name' => 'El contenido seleccionado está deshabilitado y conserva únicamente su historial.']);
        }
    }
}

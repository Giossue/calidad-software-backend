<?php

namespace App\Actions\Teacher;

use App\Models\AsignaturaTutoria;
use App\Models\Asistencia;
use App\Models\InscripcionTutoria;
use App\Models\Tema;
use App\Models\TutoringSession;
use App\Models\Usuario;
use App\Support\TeacherWorkspace;
use Illuminate\Validation\ValidationException;

class SaveSession
{
    public function __construct(private TeacherWorkspace $workspace) {}

    /** @param array{date: string, topics_covered: bool, topic_ids?: array<int, int>, attendance: array<int, array{enrollment_id: int, present: bool}>} $data */
    public function execute(Usuario $teacher, AsignaturaTutoria $tutoring, array $data): TutoringSession
    {
        return $this->workspace->write($teacher, $tutoring, function () use ($teacher, $tutoring, $data): TutoringSession {
            $enrollments = InscripcionTutoria::query()->where('fk_asig_tutoria', $tutoring->getKey())->where('estado', true)
                ->whereIn('id_inscripcion', array_column($data['attendance'], 'enrollment_id'))->lockForUpdate()->with('estudiante.roles')->get()->keyBy('id_inscripcion');
            if ($enrollments->count() !== count($data['attendance']) || $enrollments->contains(fn (InscripcionTutoria $enrollment) => ! $enrollment->estudiante->estado || ! $enrollment->estudiante->hasRole('estudiante'))) {
                throw ValidationException::withMessages(['attendance' => 'La asistencia debe corresponder a estudiantes activos inscritos en esta tutoría.']);
            }
            $session = TutoringSession::query()->where('tutoring_id', $tutoring->getKey())->whereDate('date', $data['date'])->first()
                ?? new TutoringSession(['tutoring_id' => $tutoring->getKey(), 'date' => $data['date']]);
            if ($session->exists && $session->date->toDateString() < today()->toDateString()) {
                throw ValidationException::withMessages(['date' => 'Las sesiones de fechas anteriores son de solo consulta y ya no se pueden modificar.']);
            }
            $oldTopics = $session->exists ? $session->topics()->pluck('id_tema')->all() : [];
            $topicIds = $data['topics_covered'] ? ($data['topic_ids'] ?? []) : [];
            $topics = $tutoring->temas()->whereIn('id_tema', $topicIds)->get();
            if ($topics->count() !== count($topicIds) || $topics->contains(fn (Tema $topic) => ! $topic->estado && ! in_array($topic->getKey(), $oldTopics, true))) {
                throw ValidationException::withMessages(['topic_ids' => 'Selecciona temas activos de esta tutoría.']);
            }
            $session->fill(['recorded_by' => $teacher->getKey(), 'topics_covered' => $data['topics_covered']])->save();
            $session->topics()->sync($topicIds);
            foreach ($data['attendance'] as $record) {
                $enrollment = $enrollments->get($record['enrollment_id']);
                $recordModel = Asistencia::query()->where('fk_inscripcion', $enrollment->getKey())->whereDate('fecha', $data['date'])->first()
                    ?? new Asistencia(['fk_inscripcion' => $enrollment->getKey(), 'fecha' => $data['date']]);
                $recordModel->fill([
                    'fk_id_usuario' => $enrollment->fk_id_usuario, 'estado_asistencia' => $record['present'], 'session_id' => $session->getKey(),
                ])->save();
            }
            foreach (array_unique([...$oldTopics, ...$topicIds]) as $topicId) {
                $topic = Tema::query()->whereKey($topicId)->firstOrFail();
                $topic->update(['visto' => $topic->sessions()->where('topics_covered', true)->exists()]);
            }

            return $session->load('topics', 'attendance.estudiante');
        });
    }
}

<?php

namespace Tests\Feature\Api\V1\Teacher;

use App\Models\Actividad;
use App\Models\Metodologia;
use App\Models\Tema;
use App\Models\TutoringSession;

class DeleteSessionAndContentTest extends TeacherTestCase
{
    public function test_a_wrongly_registered_session_is_deleted_with_its_attendance_and_its_topics_are_kept(): void
    {
        $this->classNow();
        $enrollment = $this->enrollment();
        $topic = $this->postJson($this->path('/topics'), ['name' => 'Pruebas unitarias'])->assertCreated()->json('data.id');
        $sessionId = $this->putJson($this->path('/sessions'), [
            'date' => '2026-09-29', 'topics_covered' => true, 'topic_ids' => [$topic],
            'attendance' => [['enrollment_id' => $enrollment->getKey(), 'present' => true]],
        ])->assertSuccessful()->json('data.id');
        $this->assertTrue(Tema::query()->findOrFail($topic)->visto);

        $this->deleteJson($this->path("/sessions/{$sessionId}"))->assertNoContent();

        $this->assertDatabaseMissing('tutoring_sessions', ['id' => $sessionId]);
        $this->assertDatabaseMissing('asistencia', ['session_id' => $sessionId]);
        $this->assertDatabaseMissing('tutoring_session_topic', ['session_id' => $sessionId]);
        // El tema se conserva, ahora sin sesión y ya no marcado como visto.
        $this->assertFalse(Tema::query()->findOrFail($topic)->visto);
    }

    public function test_a_topic_still_covered_in_another_session_stays_seen(): void
    {
        $topic = Tema::query()->create(['fk_asig_tutoria' => $this->tutoring->getKey(), 'nombre' => 'Compartido', 'estado' => true, 'visto' => true]);
        $first = TutoringSession::query()->create(['tutoring_id' => $this->tutoring->getKey(), 'recorded_by' => $this->teacher->getKey(), 'date' => '2026-09-21', 'topics_covered' => true]);
        $second = TutoringSession::query()->create(['tutoring_id' => $this->tutoring->getKey(), 'recorded_by' => $this->teacher->getKey(), 'date' => '2026-09-28', 'topics_covered' => true]);
        $first->topics()->attach($topic->getKey());
        $second->topics()->attach($topic->getKey());

        $this->deleteJson($this->path("/sessions/{$first->getKey()}"))->assertNoContent();

        $this->assertTrue($topic->refresh()->visto);
        $this->assertDatabaseHas('tutoring_session_topic', ['session_id' => $second->getKey(), 'topic_id' => $topic->getKey()]);
    }

    public function test_sessions_of_another_tutoring_or_a_closed_period_cannot_be_deleted(): void
    {
        $foreign = $this->createTutoring($this->otherCycle);
        $foreignSession = TutoringSession::query()->create(['tutoring_id' => $foreign->getKey(), 'recorded_by' => $this->teacher->getKey(), 'date' => '2026-09-21', 'topics_covered' => false]);
        $this->deleteJson($this->path("/sessions/{$foreignSession->getKey()}"))->assertNotFound();
        $this->assertDatabaseHas('tutoring_sessions', ['id' => $foreignSession->getKey()]);

        $session = TutoringSession::query()->create(['tutoring_id' => $this->tutoring->getKey(), 'recorded_by' => $this->teacher->getKey(), 'date' => '2026-09-21', 'topics_covered' => false]);
        $this->period->update(['estado' => false]);
        $this->deleteJson($this->path("/sessions/{$session->getKey()}"))->assertForbidden();
        $this->assertDatabaseHas('tutoring_sessions', ['id' => $session->getKey()]);
    }

    public function test_an_activity_is_deleted_with_its_methodologies_and_a_methodology_alone(): void
    {
        $topic = $this->postJson($this->path('/topics'), ['name' => 'Tema'])->assertCreated()->json('data.id');
        $wrong = $this->postJson($this->path("/topics/{$topic}/activities"), ['name' => 'Actividad mal escrita', 'duration' => '30 minutos'])->assertCreated()->json('data.id');
        $kept = $this->postJson($this->path("/topics/{$topic}/activities"), ['name' => 'Actividad correcta', 'duration' => '1 hora'])->assertCreated()->json('data.id');
        $this->postJson($this->path("/topics/{$topic}/activities/{$wrong}/methodologies"), ['description' => 'Trabajo en grupo'])->assertCreated();
        $methodOk = $this->postJson($this->path("/topics/{$topic}/activities/{$kept}/methodologies"), ['description' => 'Exposición'])->assertCreated()->json('data.id');
        $methodWrong = $this->postJson($this->path("/topics/{$topic}/activities/{$kept}/methodologies"), ['description' => 'Metodología duplicada'])->assertCreated()->json('data.id');

        $this->deleteJson($this->path("/topics/{$topic}/activities/{$wrong}"))->assertNoContent();
        $this->deleteJson($this->path("/topics/{$topic}/activities/{$kept}/methodologies/{$methodWrong}"))->assertNoContent();

        $this->assertDatabaseMissing('actividad', ['id_actividad' => $wrong]);
        $this->assertSame(0, Metodologia::query()->where('fk_actividad', $wrong)->count());
        $this->assertDatabaseHas('actividad', ['id_actividad' => $kept]);
        $this->assertSame([$methodOk], Metodologia::query()->where('fk_actividad', $kept)->pluck('id_metodologia')->all());
    }

    public function test_content_of_another_topic_or_a_closed_period_cannot_be_deleted(): void
    {
        $topic = Tema::query()->create(['fk_asig_tutoria' => $this->tutoring->getKey(), 'nombre' => 'Uno', 'estado' => true]);
        $other = Tema::query()->create(['fk_asig_tutoria' => $this->tutoring->getKey(), 'nombre' => 'Otro', 'estado' => true]);
        $activity = Actividad::query()->create(['fk_tema' => $other->getKey(), 'nombre' => 'Ajena', 'duracion' => '30 minutos', 'estado' => true]);
        // La actividad no es del tema indicado en la ruta.
        $this->deleteJson($this->path("/topics/{$topic->getKey()}/activities/{$activity->getKey()}"))->assertNotFound();
        $this->assertDatabaseHas('actividad', ['id_actividad' => $activity->getKey()]);

        $this->period->update(['estado' => false]);
        $this->deleteJson($this->path("/topics/{$other->getKey()}/activities/{$activity->getKey()}"))->assertForbidden();
        $this->assertDatabaseHas('actividad', ['id_actividad' => $activity->getKey()]);
    }
}

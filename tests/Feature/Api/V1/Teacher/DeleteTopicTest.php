<?php

namespace Tests\Feature\Api\V1\Teacher;

use App\Models\Tema;
use App\Models\TutoringSession;
use App\Support\TopicFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DeleteTopicTest extends TeacherTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_a_wrongly_registered_topic_is_deleted_with_its_content_and_session_link(): void
    {
        $session = TutoringSession::query()->create(['tutoring_id' => $this->tutoring->getKey(), 'recorded_by' => $this->teacher->getKey(), 'date' => '2026-09-21', 'topics_covered' => false]);
        $wrong = $this->postJson($this->path('/topics'), ['name' => 'Tema mal escrito'])->assertCreated()->json('data.id');
        $kept = $this->postJson($this->path('/topics'), ['name' => 'Tema correcto'])->assertCreated()->json('data.id');
        $activity = $this->postJson($this->path("/topics/{$wrong}/activities"), ['name' => 'Actividad', 'duration' => '30 minutos'])->assertCreated()->json('data.id');
        $this->postJson($this->path("/topics/{$wrong}/activities/{$activity}/methodologies"), ['description' => 'Trabajo en grupo'])->assertCreated();
        $this->post($this->path("/topics/{$wrong}/files"), ['file' => UploadedFile::fake()->create('guia.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertCreated();
        $path = Tema::query()->findOrFail($wrong)->files()->firstOrFail()->path;
        $this->patchJson($this->path("/sessions/{$session->getKey()}/topics"), ['topic_ids' => [$wrong, $kept]])->assertOk();

        $this->deleteJson($this->path("/topics/{$wrong}"))->assertNoContent();

        $this->assertDatabaseMissing('tema', ['id_tema' => $wrong]);
        $this->assertDatabaseCount('actividad', 0);
        $this->assertDatabaseCount('metodologia', 0);
        $this->assertDatabaseCount('topic_files', 0);
        $this->assertDatabaseMissing('tutoring_session_topic', ['topic_id' => $wrong]);
        $this->assertDatabaseHas('tutoring_session_topic', ['session_id' => $session->getKey(), 'topic_id' => $kept]);
        $this->assertTrue($session->refresh()->topics_covered);
        TopicFiles::disk()->assertMissing($path);
        $this->getJson($this->path('/topics'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Tema correcto');
    }

    public function test_a_session_left_without_topics_is_no_longer_marked_as_covering_topics(): void
    {
        $session = TutoringSession::query()->create(['tutoring_id' => $this->tutoring->getKey(), 'recorded_by' => $this->teacher->getKey(), 'date' => '2026-09-21', 'topics_covered' => false]);
        $topic = $this->postJson($this->path('/topics'), ['name' => 'Único tema'])->assertCreated()->json('data.id');
        $this->patchJson($this->path("/sessions/{$session->getKey()}/topics"), ['topic_ids' => [$topic]])->assertOk();

        $this->deleteJson($this->path("/topics/{$topic}"))->assertNoContent();

        $this->assertFalse($session->refresh()->topics_covered);
    }

    public function test_topics_of_a_closed_period_cannot_be_deleted(): void
    {
        $topic = Tema::query()->create(['fk_asig_tutoria' => $this->tutoring->getKey(), 'nombre' => 'Histórico', 'estado' => true]);
        $this->period->update(['estado' => false]);

        $this->deleteJson($this->path('/topics/'.$topic->getKey()))->assertForbidden();

        $this->assertDatabaseHas('tema', ['id_tema' => $topic->getKey()]);
    }

    public function test_topics_of_another_tutoring_cannot_be_deleted(): void
    {
        $foreign = $this->createTutoring($this->otherCycle);
        $topic = Tema::query()->create(['fk_asig_tutoria' => $foreign->getKey(), 'nombre' => 'Ajeno', 'estado' => true]);

        $this->deleteJson($this->path('/topics/'.$topic->getKey()))->assertNotFound();

        $this->assertDatabaseHas('tema', ['id_tema' => $topic->getKey()]);
    }
}

<?php

namespace Tests\Feature\Api\V1\Teacher;

use App\Models\Actividad;
use App\Models\Metodologia;
use App\Models\Tema;

class ContentManagementTest extends TeacherTestCase
{
    public function test_topics_activities_and_methodologies_support_the_complete_lifecycle(): void
    {
        $topic = $this->postJson($this->path('/topics'), ['name' => 'Pruebas', 'description' => 'Pruebas de software'])->assertCreated()->json('data.id');
        $this->patchJson($this->path("/topics/{$topic}"), ['name' => 'Pruebas funcionales', 'description' => 'Casos de prueba'])->assertOk();
        $activity = $this->postJson($this->path("/topics/{$topic}/activities"), ['name' => 'Diseñar casos', 'duration' => '30 minutos'])->assertCreated()->json('data.id');
        $this->patchJson($this->path("/topics/{$topic}/activities/{$activity}"), ['name' => 'Ejecutar casos', 'duration' => '45 minutos'])->assertOk();
        $method = $this->postJson($this->path("/topics/{$topic}/activities/{$activity}/methodologies"), ['description' => 'Trabajo colaborativo'])->assertCreated()->json('data.id');
        $this->patchJson($this->path("/topics/{$topic}/activities/{$activity}/methodologies/{$method}"), ['description' => 'Aprendizaje basado en problemas'])->assertOk();
        $this->getJson($this->path('/topics'))->assertOk()->assertJsonPath('data.0.activities.0.methodologies.0.description', 'Aprendizaje basado en problemas');
        $this->patchJson($this->path("/topics/{$topic}/activities/{$activity}/methodologies/{$method}/deactivate"))->assertOk()->assertJsonPath('data.is_active', false);
        $this->patchJson($this->path("/topics/{$topic}/activities/{$activity}/deactivate"))->assertOk()->assertJsonPath('data.is_active', false);
        $this->patchJson($this->path("/topics/{$topic}/deactivate"))->assertOk()->assertJsonPath('data.is_active', false);
        $this->assertDatabaseCount('tema', 1);
        $this->assertDatabaseCount('actividad', 1);
        $this->assertDatabaseCount('metodologia', 1);
    }

    public function test_empty_duplicate_and_oversized_content_is_rejected(): void
    {
        $this->postJson($this->path('/topics'), ['name' => '   '])->assertUnprocessable();
        $id = $this->postJson($this->path('/topics'), ['name' => 'Pruebas'])->assertCreated()->json('data.id');
        $this->postJson($this->path('/topics'), ['name' => 'Pruebas'])->assertUnprocessable();
        $this->postJson($this->path("/topics/{$id}/activities"), ['name' => 'Diseñar', 'duration' => ''])->assertUnprocessable();
        $this->postJson($this->path('/topics'), ['name' => 'Otro', 'description' => str_repeat('a', 256)])->assertUnprocessable();
    }

    public function test_nested_foreign_ids_are_not_accessible_through_an_owned_tutoring(): void
    {
        $foreign = $this->createTutoring($this->otherCycle);
        $foreignTopic = Tema::query()->create(['fk_asig_tutoria' => $foreign->getKey(), 'nombre' => 'Ajeno', 'estado' => true]);
        $ownTopic = Tema::query()->create(['fk_asig_tutoria' => $this->tutoring->getKey(), 'nombre' => 'Propio', 'estado' => true]);
        $activity = Actividad::query()->create(['fk_tema' => $foreignTopic->getKey(), 'nombre' => 'Ajena', 'duracion' => '10 minutos', 'estado' => true]);
        $method = Metodologia::query()->create(['fk_actividad' => $activity->getKey(), 'descripcion' => 'Ajena', 'estado' => true]);
        $this->patchJson($this->path('/topics/'.$foreignTopic->getKey()), ['name' => 'Alterado'])->assertNotFound();
        $this->patchJson($this->path('/topics/'.$ownTopic->getKey().'/activities/'.$activity->getKey()), ['name' => 'Alterada', 'duration' => '10 minutos'])->assertNotFound();
        $ownActivity = Actividad::query()->create(['fk_tema' => $ownTopic->getKey(), 'nombre' => 'Propia', 'duracion' => '10 minutos', 'estado' => true]);
        $this->patchJson($this->path('/topics/'.$ownTopic->getKey().'/activities/'.$ownActivity->getKey().'/methodologies/'.$method->getKey()), ['description' => 'Alterada'])->assertNotFound();
    }

    public function test_inactive_parents_cannot_receive_new_content(): void
    {
        $topic = Tema::query()->create(['fk_asig_tutoria' => $this->tutoring->getKey(), 'nombre' => 'Inactivo', 'estado' => false]);
        $this->postJson($this->path('/topics/'.$topic->getKey().'/activities'), ['name' => 'Nueva', 'duration' => '10 minutos'])->assertUnprocessable();
        $this->patchJson($this->path('/topics/'.$topic->getKey()), ['name' => 'Reactivado'])->assertUnprocessable();
        $this->getJson($this->path('/topics').'?status=inactive')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_topic_coverage_status_can_be_updated_and_toggled(): void
    {
        $topic = $this->postJson($this->path('/topics'), ['name' => 'Introducción a QA', 'description' => 'Conceptos'])->assertCreated()->json('data.id');
        $this->getJson($this->path('/topics'))->assertOk()->assertJsonPath('data.0.is_covered', false);

        // Toggle covered via endpoint
        $this->patchJson($this->path("/topics/{$topic}/toggle-covered"))->assertOk()->assertJsonPath('data.is_covered', true);
        $this->assertDatabaseHas('tema', ['id_tema' => $topic, 'visto' => true]);

        // Toggle back to false
        $this->patchJson($this->path("/topics/{$topic}/toggle-covered"))->assertOk()->assertJsonPath('data.is_covered', false);
        $this->assertDatabaseHas('tema', ['id_tema' => $topic, 'visto' => false]);

        // Update via updateTopic payload
        $this->patchJson($this->path("/topics/{$topic}"), ['is_covered' => true])->assertOk()->assertJsonPath('data.is_covered', true);
        $this->assertDatabaseHas('tema', ['id_tema' => $topic, 'visto' => true]);
    }
}

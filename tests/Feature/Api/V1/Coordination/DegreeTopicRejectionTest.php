<?php

namespace Tests\Feature\Api\V1\Coordination;

use App\Models\PeriodoAcademico;
use App\Models\TemaTitulacion;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DegreeTopicRejectionTest extends TestCase
{
    use RefreshDatabase;

    private PeriodoAcademico $period;

    private Usuario $student;

    private TemaTitulacion $topic;

    private Usuario $coordinator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->period = PeriodoAcademico::query()->create([
            'nombre' => 'PAO 2026-1',
            'fecha_inicio' => '2026-05-01',
            'fecha_fin' => '2026-09-30',
            'estado' => true,
        ]);

        $this->student = Usuario::factory()->withRole('estudiante')->create();

        $this->topic = TemaTitulacion::query()->create([
            'fk_id_usuario' => $this->student->getKey(),
            'fk_periodo' => $this->period->getKey(),
            'titulo' => 'Desarrollo de Software Web sin Arquitectura Definida',
            'descripcion' => 'Propuesta inicial con alcance insuficiente',
            'estado' => 'pendiente',
            'fecha_propuesta' => now()->toDateString(),
        ]);

        $this->coordinator = Usuario::factory()->withRole('coordinador_titulacion')->create();
    }

    public function test_coordinador_titulacion_can_reject_pending_degree_topic_with_optional_observation(): void
    {
        Sanctum::actingAs($this->coordinator);

        $observation = 'El alcance propuesto carece de fundamentación metodológica y delimitación técnica.';

        $response = $this->postJson("/api/v1/coordination/degree-topics/{$this->topic->getKey()}/reject", [
            'observation' => $observation,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $this->topic->getKey())
            ->assertJsonPath('data.status', 'rechazado')
            ->assertJsonPath('data.reviewer.id', $this->coordinator->getKey())
            ->assertJsonPath('data.reviewed_at', now()->toDateString())
            ->assertJsonCount(1, 'data.observations')
            ->assertJsonPath('data.observations.0.observation', $observation)
            ->assertJsonPath('data.observations.0.coordinator.id', $this->coordinator->getKey());

        $this->assertDatabaseHas('tema_titulacion', [
            'id_tema_tit' => $this->topic->getKey(),
            'estado' => 'rechazado',
            'fk_coord_revisor' => $this->coordinator->getKey(),
        ]);

        $this->assertDatabaseHas('observacion_titulacion', [
            'fk_tema_tit' => $this->topic->getKey(),
            'fk_coord_tit' => $this->coordinator->getKey(),
            'descripcion' => $observation,
        ]);
    }

    public function test_coordinador_titulacion_can_reject_degree_topic_without_observation(): void
    {
        Sanctum::actingAs($this->coordinator);

        $response = $this->postJson("/api/v1/coordination/degree-topics/{$this->topic->getKey()}/reject", []);

        $response->assertOk()
            ->assertJsonPath('data.id', $this->topic->getKey())
            ->assertJsonPath('data.status', 'rechazado')
            ->assertJsonPath('data.reviewer.id', $this->coordinator->getKey())
            ->assertJsonCount(0, 'data.observations');

        $this->assertDatabaseHas('tema_titulacion', [
            'id_tema_tit' => $this->topic->getKey(),
            'estado' => 'rechazado',
            'fk_coord_revisor' => $this->coordinator->getKey(),
        ]);

        $this->assertDatabaseMissing('observacion_titulacion', [
            'fk_tema_tit' => $this->topic->getKey(),
        ]);
    }

    public function test_coordinador_titulacion_can_add_standalone_observation_to_degree_topic(): void
    {
        Sanctum::actingAs($this->coordinator);

        $comment = 'Revisar antecedentes investigativos en el repositorio institucional de la universidad.';

        $response = $this->postJson("/api/v1/coordination/degree-topics/{$this->topic->getKey()}/observations", [
            'observation' => $comment,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.topic_id', $this->topic->getKey())
            ->assertJsonPath('data.observation', $comment)
            ->assertJsonPath('data.coordinator.id', $this->coordinator->getKey());

        $this->assertDatabaseHas('observacion_titulacion', [
            'fk_tema_tit' => $this->topic->getKey(),
            'fk_coord_tit' => $this->coordinator->getKey(),
            'descripcion' => $comment,
        ]);
    }

    public function test_student_can_view_rejected_degree_topic_and_observations(): void
    {
        // El coordinador rechaza la propuesta dejando observación
        Sanctum::actingAs($this->coordinator);
        $reason = 'Favor de replantear los objetivos específicos y justificar la metodología ágil.';
        $this->postJson("/api/v1/coordination/degree-topics/{$this->topic->getKey()}/reject", [
            'reason' => $reason,
        ])->assertOk();

        // El estudiante autenticado consulta sus temas
        Sanctum::actingAs($this->student);

        $response = $this->getJson('/api/v1/student/degree-topics');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->topic->getKey())
            ->assertJsonPath('data.0.status', 'rechazado')
            ->assertJsonPath('data.0.reviewer.id', $this->coordinator->getKey())
            ->assertJsonPath('data.0.observations.0.observation', $reason);

        // Otro estudiante no debe ver los temas de este estudiante
        $otherStudent = Usuario::factory()->withRole('estudiante')->create();
        Sanctum::actingAs($otherStudent);

        $this->getJson('/api/v1/student/degree-topics')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_cannot_reject_already_approved_topic(): void
    {
        $this->topic->update([
            'estado' => 'aprobado',
            'fecha_revision' => now()->toDateString(),
            'fk_coord_revisor' => $this->coordinator->getKey(),
        ]);

        Sanctum::actingAs($this->coordinator);

        $response = $this->postJson("/api/v1/coordination/degree-topics/{$this->topic->getKey()}/reject", [
            'observation' => 'Intento de rechazar un tema ya aprobado.',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['topic']);
    }

    public function test_cannot_reject_already_rejected_topic(): void
    {
        $this->topic->update([
            'estado' => 'rechazado',
            'fecha_revision' => now()->toDateString(),
            'fk_coord_revisor' => $this->coordinator->getKey(),
        ]);

        Sanctum::actingAs($this->coordinator);

        $response = $this->postJson("/api/v1/coordination/degree-topics/{$this->topic->getKey()}/reject", [
            'observation' => 'Intento de rechazar nuevamente un tema rechazado.',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['topic']);
    }

    public function test_rejection_observation_length_validation(): void
    {
        Sanctum::actingAs($this->coordinator);

        $longObservation = str_repeat('A', 1001);

        $this->postJson("/api/v1/coordination/degree-topics/{$this->topic->getKey()}/reject", [
            'observation' => $longObservation,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['observation']);
    }

    public function test_unauthorized_user_cannot_reject_degree_topic(): void
    {
        Sanctum::actingAs($this->student);

        $this->postJson("/api/v1/coordination/degree-topics/{$this->topic->getKey()}/reject", [
            'observation' => 'Un estudiante no tiene permisos de rechazo.',
        ])->assertForbidden();

        $this->postJson("/api/v1/coordination/degree-topics/{$this->topic->getKey()}/observations", [
            'observation' => 'Un estudiante no puede agregar observaciones.',
        ])->assertForbidden();
    }
}

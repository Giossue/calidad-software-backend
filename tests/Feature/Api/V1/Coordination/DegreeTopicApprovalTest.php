<?php

namespace Tests\Feature\Api\V1\Coordination;

use App\Models\PeriodoAcademico;
use App\Models\TemaTitulacion;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DegreeTopicApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordinador_titulacion_can_approve_degree_topic_assigning_tutor_and_peers(): void
    {
        $period = PeriodoAcademico::query()->create([
            'nombre' => 'PAO 2026-1',
            'fecha_inicio' => '2026-05-01',
            'fecha_fin' => '2026-09-30',
            'estado' => true,
        ]);

        $student = Usuario::factory()->withRole('estudiante')->create();

        $topic = TemaTitulacion::query()->create([
            'fk_id_usuario' => $student->getKey(),
            'fk_periodo' => $period->getKey(),
            'titulo' => 'Auditoría de Seguridad en Aplicaciones Web',
            'descripcion' => 'Propuesta de desarrollo de software',
            'estado' => 'pendiente',
            'fecha_propuesta' => now()->toDateString(),
        ]);

        $tutor = Usuario::factory()->withRole('docente')->create(['nombre' => 'Ing. Docente Tutor']);
        $peer1 = Usuario::factory()->withRole('docente')->create(['nombre' => 'Ing. Par Académico 1']);
        $peer2 = Usuario::factory()->withRole('docente')->create(['nombre' => 'Ing. Par Académico 2']);

        $coordinator = Usuario::factory()->withRole('coordinador_titulacion')->create();
        Sanctum::actingAs($coordinator);

        $response = $this->postJson("/api/v1/coordination/degree-topics/{$topic->getKey()}/approve", [
            'tutor_id' => $tutor->getKey(),
            'peer_ids' => [$peer1->getKey(), $peer2->getKey()],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $topic->getKey())
            ->assertJsonPath('data.status', 'aprobado')
            ->assertJsonPath('data.reviewer.id', $coordinator->getKey())
            ->assertJsonCount(3, 'data.assignments');

        // Verificación en base de datos
        $this->assertDatabaseHas('tema_titulacion', [
            'id_tema_tit' => $topic->getKey(),
            'estado' => 'aprobado',
            'fk_coord_revisor' => $coordinator->getKey(),
        ]);

        $this->assertDatabaseHas('asignacion_docente', [
            'fk_tema_tit' => $topic->getKey(),
            'fk_id_usuario' => $tutor->getKey(),
            'rol' => 'tutor',
            'estado' => true,
        ]);

        $this->assertDatabaseHas('asignacion_docente', [
            'fk_tema_tit' => $topic->getKey(),
            'fk_id_usuario' => $peer1->getKey(),
            'rol' => 'par_academico',
            'estado' => true,
        ]);

        $this->assertDatabaseHas('asignacion_docente', [
            'fk_tema_tit' => $topic->getKey(),
            'fk_id_usuario' => $peer2->getKey(),
            'rol' => 'par_academico',
            'estado' => true,
        ]);
    }

    public function test_cannot_assign_same_teacher_as_tutor_and_peer(): void
    {
        $period = PeriodoAcademico::query()->create([
            'nombre' => 'PAO 2026-1',
            'fecha_inicio' => '2026-05-01',
            'fecha_fin' => '2026-09-30',
            'estado' => true,
        ]);

        $student = Usuario::factory()->withRole('estudiante')->create();

        $topic = TemaTitulacion::query()->create([
            'fk_id_usuario' => $student->getKey(),
            'fk_periodo' => $period->getKey(),
            'titulo' => 'Tema Prueba Conflicto',
            'estado' => 'pendiente',
        ]);

        $teacher = Usuario::factory()->withRole('docente')->create();
        $coordinator = Usuario::factory()->withRole('coordinador_titulacion')->create();
        Sanctum::actingAs($coordinator);

        $response = $this->postJson("/api/v1/coordination/degree-topics/{$topic->getKey()}/approve", [
            'tutor_id' => $teacher->getKey(),
            'peer_ids' => [$teacher->getKey()],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['peer_ids.0']);
    }

    public function test_cannot_assign_non_teacher_users_as_tutor_or_peer(): void
    {
        $period = PeriodoAcademico::query()->create([
            'nombre' => 'PAO 2026-1',
            'fecha_inicio' => '2026-05-01',
            'fecha_fin' => '2026-09-30',
            'estado' => true,
        ]);

        $student = Usuario::factory()->withRole('estudiante')->create();

        $topic = TemaTitulacion::query()->create([
            'fk_id_usuario' => $student->getKey(),
            'fk_periodo' => $period->getKey(),
            'titulo' => 'Tema Prueba Roles',
            'estado' => 'pendiente',
        ]);

        $studentAsTutor = Usuario::factory()->withRole('estudiante')->create();
        $coordinator = Usuario::factory()->withRole('coordinador_titulacion')->create();
        Sanctum::actingAs($coordinator);

        $response = $this->postJson("/api/v1/coordination/degree-topics/{$topic->getKey()}/approve", [
            'tutor_id' => $studentAsTutor->getKey(),
            'peer_ids' => [$studentAsTutor->getKey()],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['tutor_id']);
    }

    public function test_cannot_approve_already_approved_topic(): void
    {
        $period = PeriodoAcademico::query()->create([
            'nombre' => 'PAO 2026-1',
            'fecha_inicio' => '2026-05-01',
            'fecha_fin' => '2026-09-30',
            'estado' => true,
        ]);

        $student = Usuario::factory()->withRole('estudiante')->create();

        $topic = TemaTitulacion::query()->create([
            'fk_id_usuario' => $student->getKey(),
            'fk_periodo' => $period->getKey(),
            'titulo' => 'Tema Ya Aprobado',
            'estado' => 'aprobado',
            'fecha_revision' => now()->toDateString(),
        ]);

        $tutor = Usuario::factory()->withRole('docente')->create();
        $peer = Usuario::factory()->withRole('docente')->create();
        $coordinator = Usuario::factory()->withRole('coordinador_titulacion')->create();
        Sanctum::actingAs($coordinator);

        $response = $this->postJson("/api/v1/coordination/degree-topics/{$topic->getKey()}/approve", [
            'tutor_id' => $tutor->getKey(),
            'peer_ids' => [$peer->getKey()],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['topic']);
    }

    public function test_unauthorized_user_cannot_approve_topic(): void
    {
        $period = PeriodoAcademico::query()->create([
            'nombre' => 'PAO 2026-1',
            'fecha_inicio' => '2026-05-01',
            'fecha_fin' => '2026-09-30',
            'estado' => true,
        ]);

        $student = Usuario::factory()->withRole('estudiante')->create();

        $topic = TemaTitulacion::query()->create([
            'fk_id_usuario' => $student->getKey(),
            'fk_periodo' => $period->getKey(),
            'titulo' => 'Tema Bloqueado',
            'estado' => 'pendiente',
        ]);

        $tutor = Usuario::factory()->withRole('docente')->create();
        $peer = Usuario::factory()->withRole('docente')->create();

        Sanctum::actingAs($student);

        $response = $this->postJson("/api/v1/coordination/degree-topics/{$topic->getKey()}/approve", [
            'tutor_id' => $tutor->getKey(),
            'peer_ids' => [$peer->getKey()],
        ]);

        $response->assertForbidden();
    }
}

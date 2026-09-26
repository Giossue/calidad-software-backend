<?php

namespace Tests\Feature\Api\V1\Coordination;

use App\Models\AsignacionDocente;
use App\Models\PeriodoAcademico;
use App\Models\TemaTitulacion;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AcademicPeerAssignmentTest extends TestCase
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
            'titulo' => 'Evaluación de Calidad de Software con Métricas ISO 25010',
            'descripcion' => 'Investigación aplicada a sistemas universitarios',
            'estado' => 'aprobado',
            'fecha_propuesta' => now()->toDateString(),
            'fecha_revision' => now()->toDateString(),
        ]);

        $this->coordinator = Usuario::factory()->withRole('coordinador_titulacion')->create();
    }

    public function test_coordinador_titulacion_can_list_academic_peers_assigned_to_degree_topic(): void
    {
        $tutor = Usuario::factory()->withRole('docente')->create(['nombre' => 'Tutor Responsable']);
        $peer1 = Usuario::factory()->withRole('docente')->create(['nombre' => 'Par Académico 1']);
        $peer2 = Usuario::factory()->withRole('docente')->create(['nombre' => 'Par Académico 2']);

        // Crear asignación de tutor y 2 pares
        AsignacionDocente::query()->create([
            'fk_tema_tit' => $this->topic->getKey(),
            'fk_id_usuario' => $tutor->getKey(),
            'rol' => 'tutor',
            'fecha_asignacion' => now()->toDateString(),
            'estado' => true,
        ]);

        AsignacionDocente::query()->create([
            'fk_tema_tit' => $this->topic->getKey(),
            'fk_id_usuario' => $peer1->getKey(),
            'rol' => 'par_academico',
            'fecha_asignacion' => now()->toDateString(),
            'estado' => true,
        ]);

        AsignacionDocente::query()->create([
            'fk_tema_tit' => $this->topic->getKey(),
            'fk_id_usuario' => $peer2->getKey(),
            'rol' => 'par_academico',
            'fecha_asignacion' => now()->toDateString(),
            'estado' => true,
        ]);

        Sanctum::actingAs($this->coordinator);

        $response = $this->getJson("/api/v1/coordination/degree-topics/{$this->topic->getKey()}/peers");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'assignment_id',
                        'teacher_id',
                        'identification',
                        'name',
                        'email',
                        'phone',
                        'role',
                        'assigned_at',
                        'is_active',
                    ],
                ],
            ]);

        // Asegurarse de que el tutor no esté en el listado de pares
        $teacherIds = collect($response->json('data'))->pluck('teacher_id')->all();
        $this->assertContains($peer1->getKey(), $teacherIds);
        $this->assertContains($peer2->getKey(), $teacherIds);
        $this->assertNotContains($tutor->getKey(), $teacherIds);
    }

    public function test_coordinador_titulacion_can_update_and_reassign_academic_peers(): void
    {
        $tutor = Usuario::factory()->withRole('docente')->create(['nombre' => 'Docente Tutor']);
        $oldPeer = Usuario::factory()->withRole('docente')->create(['nombre' => 'Docente Par Antiguo']);

        AsignacionDocente::query()->create([
            'fk_tema_tit' => $this->topic->getKey(),
            'fk_id_usuario' => $tutor->getKey(),
            'rol' => 'tutor',
            'fecha_asignacion' => now()->toDateString(),
            'estado' => true,
        ]);

        AsignacionDocente::query()->create([
            'fk_tema_tit' => $this->topic->getKey(),
            'fk_id_usuario' => $oldPeer->getKey(),
            'rol' => 'par_academico',
            'fecha_asignacion' => now()->toDateString(),
            'estado' => true,
        ]);

        $newPeer1 = Usuario::factory()->withRole('docente')->create(['nombre' => 'Nuevo Par 1']);
        $newPeer2 = Usuario::factory()->withRole('docente')->create(['nombre' => 'Nuevo Par 2']);

        Sanctum::actingAs($this->coordinator);

        $response = $this->putJson("/api/v1/coordination/degree-topics/{$this->topic->getKey()}/peers", [
            'peer_ids' => [$newPeer1->getKey(), $newPeer2->getKey()],
        ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        // Verificar que el par antiguo ya no está asignado
        $this->assertDatabaseMissing('asignacion_docente', [
            'fk_tema_tit' => $this->topic->getKey(),
            'fk_id_usuario' => $oldPeer->getKey(),
            'rol' => 'par_academico',
        ]);

        // Verificar que los nuevos pares están asignados
        $this->assertDatabaseHas('asignacion_docente', [
            'fk_tema_tit' => $this->topic->getKey(),
            'fk_id_usuario' => $newPeer1->getKey(),
            'rol' => 'par_academico',
            'estado' => true,
        ]);

        $this->assertDatabaseHas('asignacion_docente', [
            'fk_tema_tit' => $this->topic->getKey(),
            'fk_id_usuario' => $newPeer2->getKey(),
            'rol' => 'par_academico',
            'estado' => true,
        ]);

        // El tutor se mantiene intacto
        $this->assertDatabaseHas('asignacion_docente', [
            'fk_tema_tit' => $this->topic->getKey(),
            'fk_id_usuario' => $tutor->getKey(),
            'rol' => 'tutor',
            'estado' => true,
        ]);
    }

    public function test_cannot_assign_tutor_as_academic_peer(): void
    {
        $tutor = Usuario::factory()->withRole('docente')->create();

        AsignacionDocente::query()->create([
            'fk_tema_tit' => $this->topic->getKey(),
            'fk_id_usuario' => $tutor->getKey(),
            'rol' => 'tutor',
            'fecha_asignacion' => now()->toDateString(),
            'estado' => true,
        ]);

        Sanctum::actingAs($this->coordinator);

        $response = $this->putJson("/api/v1/coordination/degree-topics/{$this->topic->getKey()}/peers", [
            'peer_ids' => [$tutor->getKey()],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['peer_ids.0']);
    }

    public function test_cannot_assign_non_teacher_or_inactive_user_as_academic_peer(): void
    {
        $nonTeacher = Usuario::factory()->withRole('estudiante')->create();
        $inactiveTeacher = Usuario::factory()->withRole('docente')->create(['estado' => false]);

        Sanctum::actingAs($this->coordinator);

        $response = $this->putJson("/api/v1/coordination/degree-topics/{$this->topic->getKey()}/peers", [
            'peer_ids' => [$nonTeacher->getKey(), $inactiveTeacher->getKey()],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['peer_ids.0', 'peer_ids.1']);
    }

    public function test_cannot_assign_duplicate_peers(): void
    {
        $peer = Usuario::factory()->withRole('docente')->create();

        Sanctum::actingAs($this->coordinator);

        $response = $this->putJson("/api/v1/coordination/degree-topics/{$this->topic->getKey()}/peers", [
            'peer_ids' => [$peer->getKey(), $peer->getKey()],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['peer_ids.0']);
    }

    public function test_unauthorized_user_cannot_manage_academic_peers(): void
    {
        $student = Usuario::factory()->withRole('estudiante')->create();
        $peer = Usuario::factory()->withRole('docente')->create();

        Sanctum::actingAs($student);

        $this->getJson("/api/v1/coordination/degree-topics/{$this->topic->getKey()}/peers")
            ->assertForbidden();

        $this->putJson("/api/v1/coordination/degree-topics/{$this->topic->getKey()}/peers", [
            'peer_ids' => [$peer->getKey()],
        ])->assertForbidden();
    }
}

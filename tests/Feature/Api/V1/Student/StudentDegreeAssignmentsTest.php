<?php

namespace Tests\Feature\Api\V1\Student;

use App\Models\AsignacionDocente;
use App\Models\Paralelo;
use App\Models\PeriodoAcademico;
use App\Models\TemaTitulacion;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentDegreeAssignmentsTest extends TestCase
{
    use RefreshDatabase;

    private PeriodoAcademico $period;

    private Paralelo $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->period = PeriodoAcademico::query()->create([
            'nombre' => 'PAO 2026-1',
            'fecha_inicio' => '2026-05-01',
            'fecha_fin' => '2026-09-30',
            'estado' => true,
        ]);

        $this->section = Paralelo::query()->create([
            'nombre' => 'A',
            'estado' => true,
        ]);

        $this->period->paralelos()->attach($this->section->getKey());
    }

    public function test_student_can_consult_tutor_and_peers_assigned_to_approved_topic(): void
    {
        $student = Usuario::factory()->withRole('estudiante')->create([
            'nombre' => 'Carlos Estudiante Aprobado',
            'correo' => 'carlos.aprobado@mail.com',
        ]);
        $student->paralelos()->attach($this->section->getKey(), ['fecha_asignacion' => now()]);

        $coordinator = Usuario::factory()->withRole('coordinador_titulacion')->create([
            'nombre' => 'Ing. Coordinador Revisor',
        ]);

        $topic = TemaTitulacion::query()->create([
            'fk_id_usuario' => $student->getKey(),
            'fk_periodo' => $this->period->getKey(),
            'fk_coord_revisor' => $coordinator->getKey(),
            'titulo' => 'Desarrollo de Software Seguro con Arquitectura Limpia',
            'descripcion' => 'Propuesta aprobada por comisión de titulación.',
            'estado' => 'aprobado',
            'fecha_propuesta' => '2026-05-10',
            'fecha_revision' => '2026-05-20',
        ]);

        $tutor = Usuario::factory()->withRole('docente')->create([
            'nombre' => 'Ing. Tutor Principal',
            'correo' => 'tutor@mail.com',
            'telefono' => '0981112233',
        ]);

        $peer1 = Usuario::factory()->withRole('docente')->create([
            'nombre' => 'Dra. Primer Par Académico',
            'correo' => 'par1@mail.com',
            'telefono' => '0982223344',
        ]);

        $peer2 = Usuario::factory()->withRole('docente')->create([
            'nombre' => 'Msc. Segundo Par Académico',
            'correo' => 'par2@mail.com',
            'telefono' => '0983334455',
        ]);

        // Asignaciones
        AsignacionDocente::query()->create([
            'fk_tema_tit' => $topic->getKey(),
            'fk_id_usuario' => $tutor->getKey(),
            'rol' => 'tutor',
            'fecha_asignacion' => '2026-05-21',
            'estado' => true,
        ]);

        AsignacionDocente::query()->create([
            'fk_tema_tit' => $topic->getKey(),
            'fk_id_usuario' => $peer1->getKey(),
            'rol' => 'par_academico',
            'fecha_asignacion' => '2026-05-21',
            'estado' => true,
        ]);

        AsignacionDocente::query()->create([
            'fk_tema_tit' => $topic->getKey(),
            'fk_id_usuario' => $peer2->getKey(),
            'rol' => 'par_academico',
            'fecha_asignacion' => '2026-05-21',
            'estado' => true,
        ]);

        Sanctum::actingAs($student, ['access-api']);

        // 1. Consulta general para su proceso aprobado
        $response = $this->getJson('/api/v1/student/degree-topics/assignments');

        $response->assertOk()
            ->assertJsonPath('data.topic.id', $topic->getKey())
            ->assertJsonPath('data.topic.title', 'Desarrollo de Software Seguro con Arquitectura Limpia')
            ->assertJsonPath('data.topic.status', 'aprobado')
            ->assertJsonPath('data.topic.approved_at', '2026-05-20')
            ->assertJsonPath('data.topic.coordinator.name', 'Ing. Coordinador Revisor')
            ->assertJsonPath('data.tutor.id', $tutor->getKey())
            ->assertJsonPath('data.tutor.name', 'Ing. Tutor Principal')
            ->assertJsonPath('data.tutor.email', 'tutor@mail.com')
            ->assertJsonPath('data.tutor.phone', '0981112233')
            ->assertJsonPath('data.tutor.role', 'tutor')
            ->assertJsonPath('data.tutor.assigned_at', '2026-05-21')
            ->assertJsonCount(2, 'data.peers')
            ->assertJsonPath('data.peers.0.id', $peer1->getKey())
            ->assertJsonPath('data.peers.0.name', 'Dra. Primer Par Académico')
            ->assertJsonPath('data.peers.1.id', $peer2->getKey())
            ->assertJsonPath('data.peers.1.name', 'Msc. Segundo Par Académico')
            ->assertJsonPath('data.total_teachers_assigned', 3);

        // 2. Consulta específica por el ID del tema
        $specificResponse = $this->getJson('/api/v1/student/degree-topics/'.$topic->getKey().'/assignments');
        $specificResponse->assertOk()
            ->assertJsonPath('data.tutor.name', 'Ing. Tutor Principal')
            ->assertJsonCount(2, 'data.peers');
    }

    public function test_assignments_endpoint_returns_422_if_topic_is_pending_review(): void
    {
        $student = Usuario::factory()->withRole('estudiante')->create();

        TemaTitulacion::query()->create([
            'fk_id_usuario' => $student->getKey(),
            'fk_periodo' => $this->period->getKey(),
            'titulo' => 'Propuesta Aún en Revisión por Coordinación',
            'descripcion' => 'Esperando dictamen.',
            'estado' => 'pendiente',
            'fecha_propuesta' => now()->toDateString(),
        ]);

        Sanctum::actingAs($student, ['access-api']);

        $response = $this->getJson('/api/v1/student/degree-topics/assignments');

        $response->assertUnprocessable()
            ->assertJsonPath('code', 'topic_not_approved')
            ->assertJsonPath('message', 'La asignación de tutor y pares académicos solo está disponible tras la aprobación del tema de titulación.');
    }

    public function test_assignments_endpoint_returns_422_if_topic_is_rejected(): void
    {
        $student = Usuario::factory()->withRole('estudiante')->create();

        TemaTitulacion::query()->create([
            'fk_id_usuario' => $student->getKey(),
            'fk_periodo' => $this->period->getKey(),
            'titulo' => 'Propuesta Que Resultó Rechazada',
            'descripcion' => 'Rechazada por falta de alcance.',
            'estado' => 'rechazado',
            'fecha_propuesta' => now()->subDays(5)->toDateString(),
            'fecha_revision' => now()->toDateString(),
        ]);

        Sanctum::actingAs($student, ['access-api']);

        $response = $this->getJson('/api/v1/student/degree-topics/assignments');

        $response->assertUnprocessable()
            ->assertJsonPath('code', 'topic_not_approved');
    }

    public function test_assignments_endpoint_returns_404_if_student_has_no_topics(): void
    {
        $student = Usuario::factory()->withRole('estudiante')->create();
        Sanctum::actingAs($student, ['access-api']);

        $response = $this->getJson('/api/v1/student/degree-topics/assignments');

        $response->assertNotFound()
            ->assertJsonPath('code', 'no_topic');
    }

    public function test_student_cannot_view_assignments_of_topic_belonging_to_another_student(): void
    {
        $studentA = Usuario::factory()->withRole('estudiante')->create();
        $studentB = Usuario::factory()->withRole('estudiante')->create();

        $topicA = TemaTitulacion::query()->create([
            'fk_id_usuario' => $studentA->getKey(),
            'fk_periodo' => $this->period->getKey(),
            'titulo' => 'Tema Aprobado del Estudiante A',
            'estado' => 'aprobado',
            'fecha_propuesta' => now()->toDateString(),
            'fecha_revision' => now()->toDateString(),
        ]);

        Sanctum::actingAs($studentB, ['access-api']);

        // Estudiante B no puede consultar las asignaciones del tema de A
        $response = $this->getJson('/api/v1/student/degree-topics/'.$topicA->getKey().'/assignments');
        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_assignments(): void
    {
        $this->getJson('/api/v1/student/degree-topics/assignments')
            ->assertUnauthorized();
    }

    public function test_non_student_cannot_access_student_assignments(): void
    {
        $teacher = Usuario::factory()->withRole('docente')->create();
        Sanctum::actingAs($teacher, ['access-api']);

        $this->getJson('/api/v1/student/degree-topics/assignments')
            ->assertForbidden();
    }
}

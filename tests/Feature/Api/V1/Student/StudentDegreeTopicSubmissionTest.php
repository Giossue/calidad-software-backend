<?php

namespace Tests\Feature\Api\V1\Student;

use App\Models\Paralelo;
use App\Models\PeriodoAcademico;
use App\Models\TemaTitulacion;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentDegreeTopicSubmissionTest extends TestCase
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

    public function test_student_can_submit_degree_topic_proposal_successfully(): void
    {
        $student = Usuario::factory()->withRole('estudiante')->create([
            'nombre' => 'Estudiante Titulante',
            'correo' => 'titulante@mail.com',
        ]);
        $student->paralelos()->attach($this->section->getKey(), ['fecha_asignacion' => now()]);

        Sanctum::actingAs($student);

        $response = $this->postJson('/api/v1/student/degree-topics', [
            'title' => 'Sistema Inteligente de Evaluación de Calidad de Software',
            'description' => 'Plataforma para automatizar el análisis estático y dinámico de código.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Sistema Inteligente de Evaluación de Calidad de Software')
            ->assertJsonPath('data.description', 'Plataforma para automatizar el análisis estático y dinámico de código.')
            ->assertJsonPath('data.status', 'pendiente')
            ->assertJsonPath('data.student.id', $student->getKey())
            ->assertJsonPath('data.student.name', 'Estudiante Titulante')
            ->assertJsonPath('data.section.id', $this->section->getKey())
            ->assertJsonPath('data.academic_period.id', $this->period->getKey())
            ->assertJsonPath('data.reviewer', null)
            ->assertJsonCount(0, 'data.assignments')
            ->assertJsonCount(0, 'data.observations');

        $this->assertDatabaseHas('tema_titulacion', [
            'fk_id_usuario' => $student->getKey(),
            'fk_periodo' => $this->period->getKey(),
            'titulo' => 'Sistema Inteligente de Evaluación de Calidad de Software',
            'estado' => 'pendiente',
        ]);
    }

    public function test_student_can_submit_proposal_specifying_section_explicitly(): void
    {
        $student = Usuario::factory()->withRole('estudiante')->create();
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/v1/student/degree-topics', [
            'title' => 'Auditoría de Seguridad en Aplicaciones Web Universitarias',
            'description' => 'Enfoque OWASP para el aseguramiento institucional.',
            'section_id' => $this->section->getKey(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pendiente')
            ->assertJsonPath('data.section.id', $this->section->getKey());

        $this->assertDatabaseHas('usuario_paralelo', [
            'fk_usuario' => $student->getKey(),
            'fk_paralelo' => $this->section->getKey(),
        ]);
    }

    public function test_coordination_can_see_submitted_proposal_in_pending_list(): void
    {
        $student = Usuario::factory()->withRole('estudiante')->create();
        $student->paralelos()->attach($this->section->getKey(), ['fecha_asignacion' => now()]);

        Sanctum::actingAs($student);
        $this->postJson('/api/v1/student/degree-topics', [
            'title' => 'Herramienta de Métricas de Calidad de Software',
            'description' => 'Integración con CI/CD.',
        ])->assertCreated();

        // El Coordinador de Titulación consulta los temas pendientes
        $coordinator = Usuario::factory()->withRole('coordinador_titulacion')->create();
        Sanctum::actingAs($coordinator);

        $response = $this->getJson('/api/v1/coordination/degree-topics/pending?section_id='.$this->section->getKey());

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Herramienta de Métricas de Calidad de Software')
            ->assertJsonPath('data.0.status', 'pendiente')
            ->assertJsonPath('data.0.student.id', $student->getKey());
    }

    public function test_student_cannot_submit_when_already_has_pending_proposal(): void
    {
        $student = Usuario::factory()->withRole('estudiante')->create();
        Sanctum::actingAs($student);

        // Primer propuesta
        $this->postJson('/api/v1/student/degree-topics', [
            'title' => 'Primera Propuesta de Grado',
            'description' => 'Detalle de la primera propuesta.',
        ])->assertCreated();

        // Segunda propuesta mientras la primera sigue pendiente
        $response = $this->postJson('/api/v1/student/degree-topics', [
            'title' => 'Segunda Propuesta de Grado Distinta',
            'description' => 'Intento de subir otro tema en el mismo período.',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title'])
            ->assertJsonPath('errors.title.0', 'Ya cuentas con una propuesta de tema de titulación pendiente de revisión en este período académico. Puedes cambiarla directamente o enviar "replace_pending": true para registrar una alternativa.');
    }

    public function test_student_cannot_submit_when_already_has_approved_proposal(): void
    {
        $student = Usuario::factory()->withRole('estudiante')->create();

        // Tema previamente aprobado
        TemaTitulacion::query()->create([
            'fk_id_usuario' => $student->getKey(),
            'fk_periodo' => $this->period->getKey(),
            'titulo' => 'Tema Ya Aprobado en el Período',
            'descripcion' => 'Propuesta aprobada por coordinación.',
            'estado' => 'aprobado',
            'fecha_propuesta' => now()->subDays(10)->toDateString(),
            'fecha_revision' => now()->toDateString(),
        ]);

        Sanctum::actingAs($student);

        $response = $this->postJson('/api/v1/student/degree-topics', [
            'title' => 'Intento de Nuevo Tema Teniendo Aprobado',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title'])
            ->assertJsonPath('errors.title.0', 'Ya cuentas con un tema de titulación aprobado en este período académico.');
    }

    public function test_student_can_submit_new_proposal_if_previous_was_rejected(): void
    {
        $student = Usuario::factory()->withRole('estudiante')->create();

        // Tema previamente rechazado
        TemaTitulacion::query()->create([
            'fk_id_usuario' => $student->getKey(),
            'fk_periodo' => $this->period->getKey(),
            'titulo' => 'Tema Previamente Rechazado',
            'descripcion' => 'Propuesta que no cumplió los requisitos.',
            'estado' => 'rechazado',
            'fecha_propuesta' => now()->subDays(15)->toDateString(),
            'fecha_revision' => now()->subDays(5)->toDateString(),
        ]);

        Sanctum::actingAs($student);

        // Puede enviar una nueva propuesta mejorada
        $response = $this->postJson('/api/v1/student/degree-topics', [
            'title' => 'Nueva Propuesta con Enfoque Corregido',
            'description' => 'Nueva formulación del problema y alcance adecuado.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Nueva Propuesta con Enfoque Corregido')
            ->assertJsonPath('data.status', 'pendiente');
    }

    public function test_validation_fails_with_invalid_or_missing_title(): void
    {
        $student = Usuario::factory()->withRole('estudiante')->create();
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/v1/student/degree-topics', [
            'title' => 'abc', // menor a 5 caracteres
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_unauthenticated_or_non_student_cannot_submit_proposal(): void
    {
        // No autenticado
        $this->postJson('/api/v1/student/degree-topics', [
            'title' => 'Propuesta No Autenticada',
        ])->assertUnauthorized();

        // Rol docente no autorizado
        $teacher = Usuario::factory()->withRole('docente')->create();
        Sanctum::actingAs($teacher);

        $this->postJson('/api/v1/student/degree-topics', [
            'title' => 'Propuesta Enviada por Docente',
        ])->assertForbidden();
    }

    public function test_student_without_section_is_automatically_assigned_to_section_when_submitting_proposal(): void
    {
        $student = Usuario::factory()->withRole('estudiante')->create();
        // Verificar que el estudiante arranca sin paralelos
        $this->assertDatabaseMissing('usuario_paralelo', [
            'fk_usuario' => $student->getKey(),
        ]);

        Sanctum::actingAs($student);

        $response = $this->postJson('/api/v1/student/degree-topics', [
            'title' => 'Propuesta de Titulación con Asignación Automática',
            'description' => 'Debe asignar el paralelo automáticamente.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.section.id', $this->section->getKey())
            ->assertJsonPath('data.section.name', 'A');

        $this->assertDatabaseHas('usuario_paralelo', [
            'fk_usuario' => $student->getKey(),
            'fk_paralelo' => $this->section->getKey(),
        ]);
    }

    public function test_student_can_replace_pending_proposal_with_alternative_using_replace_pending_flag(): void
    {
        $student = Usuario::factory()->withRole('estudiante')->create();
        Sanctum::actingAs($student);

        // Primer propuesta
        $first = $this->postJson('/api/v1/student/degree-topics', [
            'title' => 'Primera Propuesta que Quiero Cambiar',
            'description' => 'Idea inicial.',
        ])->assertCreated()->json('data.id');

        // Envía una nueva alternativa con replace_pending: true
        $response = $this->postJson('/api/v1/student/degree-topics', [
            'title' => 'Nueva Alternativa de Titulación Reemplazante',
            'description' => 'Nueva formulación de investigación.',
            'replace_pending' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Nueva Alternativa de Titulación Reemplazante')
            ->assertJsonPath('data.status', 'pendiente');

        // La primera propuesta queda marcada como descartada
        $this->assertDatabaseHas('tema_titulacion', [
            'id_tema_tit' => $first,
            'estado' => 'descartado',
        ]);
    }

    public function test_student_can_modify_pending_degree_topic_proposal_directly(): void
    {
        $student = Usuario::factory()->withRole('estudiante')->create();
        Sanctum::actingAs($student);

        $topic = $this->postJson('/api/v1/student/degree-topics', [
            'title' => 'Propuesta Original Pendiente',
            'description' => 'Descripción inicial.',
        ])->assertCreated()->json('data.id');

        $response = $this->patchJson('/api/v1/student/degree-topics/'.$topic, [
            'title' => 'Propuesta Modificada y Mejorada',
            'description' => 'Descripción actualizada y corregida.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $topic)
            ->assertJsonPath('data.title', 'Propuesta Modificada y Mejorada')
            ->assertJsonPath('data.description', 'Descripción actualizada y corregida.')
            ->assertJsonPath('data.status', 'pendiente');

        $this->assertDatabaseHas('tema_titulacion', [
            'id_tema_tit' => $topic,
            'titulo' => 'Propuesta Modificada y Mejorada',
        ]);
    }

    public function test_student_cannot_modify_approved_or_rejected_topic_via_patch(): void
    {
        $student = Usuario::factory()->withRole('estudiante')->create();

        $approvedTopic = TemaTitulacion::query()->create([
            'fk_id_usuario' => $student->getKey(),
            'fk_periodo' => $this->period->getKey(),
            'titulo' => 'Tema Aprobado Previamente',
            'estado' => 'aprobado',
            'fecha_propuesta' => now()->toDateString(),
            'fecha_revision' => now()->toDateString(),
        ]);

        $rejectedTopic = TemaTitulacion::query()->create([
            'fk_id_usuario' => $student->getKey(),
            'fk_periodo' => $this->period->getKey(),
            'titulo' => 'Tema Rechazado Previamente',
            'estado' => 'rechazado',
            'fecha_propuesta' => now()->toDateString(),
            'fecha_revision' => now()->toDateString(),
        ]);

        Sanctum::actingAs($student);

        $this->patchJson('/api/v1/student/degree-topics/'.$approvedTopic->getKey(), [
            'title' => 'Intento de Modificar Tema Aprobado',
        ])->assertUnprocessable()->assertJsonValidationErrors(['title']);

        $this->patchJson('/api/v1/student/degree-topics/'.$rejectedTopic->getKey(), [
            'title' => 'Intento de Modificar Tema Rechazado',
        ])->assertUnprocessable()->assertJsonValidationErrors(['title']);
    }

    public function test_student_cannot_modify_topic_belonging_to_another_student(): void
    {
        $studentA = Usuario::factory()->withRole('estudiante')->create();
        $studentB = Usuario::factory()->withRole('estudiante')->create();

        $topicA = TemaTitulacion::query()->create([
            'fk_id_usuario' => $studentA->getKey(),
            'fk_periodo' => $this->period->getKey(),
            'titulo' => 'Tema del Estudiante A',
            'estado' => 'pendiente',
            'fecha_propuesta' => now()->toDateString(),
        ]);

        Sanctum::actingAs($studentB);

        $this->patchJson('/api/v1/student/degree-topics/'.$topicA->getKey(), [
            'title' => 'Estudiante B intentando usurpar tema de A',
        ])->assertForbidden();
    }
}

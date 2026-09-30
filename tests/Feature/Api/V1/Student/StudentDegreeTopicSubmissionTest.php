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
            ->assertJsonPath('errors.title.0', 'Ya cuentas con una propuesta de tema de titulación pendiente de revisión en este período académico.');
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
}

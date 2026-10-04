<?php

namespace Tests\Feature\Api\V1\Tutoring;

use App\Models\Carrera;
use App\Models\Facultad;
use App\Models\MatriculaTitulacion;
use App\Models\PeriodoAcademico;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoordinatorDegreeEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private PeriodoAcademico $period;

    private Carrera $career;

    private Usuario $coordinator;

    private Usuario $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->period = PeriodoAcademico::query()->create([
            'estado' => true,
            'nombre' => 'PAO 2026-II',
            'fecha_inicio' => '2026-10-01',
            'fecha_fin' => '2027-02-28',
        ]);

        $faculty = Facultad::query()->create(['nombre' => 'Facultad de Ingeniería', 'estado' => true]);
        $this->career = Carrera::query()->create([
            'nombre' => 'Ingeniería en Software',
            'fk_facultad' => $faculty->getKey(),
            'estado' => true,
        ]);

        $this->coordinator = Usuario::factory()->withRole('coordinador_carrera')->create([
            'nombre' => 'Coordinador Software',
            'correo' => 'coord.soft@ueb.edu.ec',
            'estado' => true,
        ]);
        $this->coordinator->coordinatedCareers()->attach($this->career->getKey());

        $this->student = Usuario::factory()->withRole('estudiante')->create([
            'nombre' => 'Estudiante Titulando',
            'correo' => 'titulando@ueb.edu.ec',
            'cedula' => '0201234567',
            'fk_carrera' => $this->career->getKey(),
            'estado' => true,
        ]);
    }

    public function test_coordinator_can_list_students_and_their_degree_enrollment_status(): void
    {
        Sanctum::actingAs($this->coordinator, ['access-api']);

        $response = $this->getJson('/api/v1/tutoring-coordination/degree-students');

        $response->assertOk()
            ->assertJsonPath('meta.current_period.name', 'PAO 2026-II')
            ->assertJsonPath('data.0.student_id', $this->student->getKey())
            ->assertJsonPath('data.0.is_degree_enrolled', false);
    }

    public function test_coordinator_can_enroll_and_unenroll_student_in_degree(): void
    {
        Sanctum::actingAs($this->coordinator, ['access-api']);

        // 1. Matricular al estudiante en titulación
        $enrollResponse = $this->postJson("/api/v1/tutoring-coordination/degree-students/{$this->student->getKey()}/enroll");
        $enrollResponse->assertOk()
            ->assertJsonPath('data.is_degree_enrolled', true)
            ->assertJsonPath('data.student_id', $this->student->getKey());

        $this->assertDatabaseHas('matricula_titulacion', [
            'fk_estudiante' => $this->student->getKey(),
            'fk_periodo' => $this->period->getKey(),
            'estado' => true,
        ]);

        // 2. Comprobar status de matrícula como estudiante
        Sanctum::actingAs($this->student, ['access-api']);
        $statusResponse = $this->getJson('/api/v1/student/degree-enrollment-status');
        $statusResponse->assertOk()
            ->assertJsonPath('is_enrolled', true)
            ->assertJsonPath('period_id', $this->period->getKey())
            ->assertJsonPath('period_name', 'PAO 2026-II');

        // 3. Dar de baja de titulación
        Sanctum::actingAs($this->coordinator, ['access-api']);
        $unenrollResponse = $this->deleteJson("/api/v1/tutoring-coordination/degree-students/{$this->student->getKey()}/unenroll");
        $unenrollResponse->assertOk()
            ->assertJsonPath('data.is_degree_enrolled', false);

        $this->assertDatabaseHas('matricula_titulacion', [
            'fk_estudiante' => $this->student->getKey(),
            'fk_periodo' => $this->period->getKey(),
            'estado' => false,
        ]);

        // 4. Comprobar que el estudiante ya no está matriculado
        Sanctum::actingAs($this->student, ['access-api']);
        $statusResponse2 = $this->getJson('/api/v1/student/degree-enrollment-status');
        $statusResponse2->assertOk()
            ->assertJsonPath('is_enrolled', false);
    }

    public function test_unenrolled_student_cannot_submit_topic_when_matriculas_exist_for_period(): void
    {
        // Crear matrícula para otro estudiante en el período
        $other = Usuario::factory()->withRole('estudiante')->create(['estado' => true]);
        MatriculaTitulacion::query()->create([
            'fk_estudiante' => $other->getKey(),
            'fk_periodo' => $this->period->getKey(),
            'fk_carrera' => $this->career->getKey(),
            'estado' => true,
        ]);

        // Estudiante no matriculado intenta enviar tema
        Sanctum::actingAs($this->student, ['access-api']);
        $response = $this->postJson('/api/v1/student/degree-topics', [
            'title' => 'Propuesta No Autorizada',
            'description' => 'Descripción del tema.',
            'academic_period_id' => $this->period->getKey(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['academic_period_id']);
    }

    public function test_coordinator_cannot_see_or_enroll_students_from_other_careers(): void
    {
        $otherCareer = Carrera::query()->create([
            'nombre' => 'Comunicación Social',
            'fk_facultad' => $this->career->fk_facultad,
            'estado' => true,
        ]);

        $studentOther = Usuario::factory()->withRole('estudiante')->create([
            'nombre' => 'Estudiante de Comunicación',
            'correo' => 'comunicacion@ueb.edu.ec',
            'cedula' => '0209991111',
            'fk_carrera' => $otherCareer->getKey(),
            'estado' => true,
        ]);

        Sanctum::actingAs($this->coordinator, ['access-api']);

        // No debe aparecer en el listado del coordinador de software
        $response = $this->getJson('/api/v1/tutoring-coordination/degree-students');
        $response->assertOk();
        $studentIds = collect($response->json('data'))->pluck('student_id');
        $this->assertContains($this->student->getKey(), $studentIds);
        $this->assertNotContains($studentOther->getKey(), $studentIds);

        // Intentar matricular directamente debe arrojar error 422
        $enrollResponse = $this->postJson("/api/v1/tutoring-coordination/degree-students/{$studentOther->getKey()}/enroll");
        $enrollResponse->assertStatus(422);
    }
}

<?php

namespace Tests\Feature\Api\V1\Student;

use App\Models\AsignaturaTutoria;
use App\Models\Carrera;
use App\Models\Ciclo;
use App\Models\Facultad;
use App\Models\InscripcionTutoria;
use App\Models\MetricaConocimiento;
use App\Models\Modalidad;
use App\Models\Nota;
use App\Models\Paralelo;
use App\Models\PeriodoAcademico;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentTutoringGradesTest extends TestCase
{
    use RefreshDatabase;

    private function createTutoringSetup(): array
    {
        $student = Usuario::factory()->withRole('estudiante')->create([
            'nombre' => 'María Estudiante',
            'correo' => 'maria.estudiante@mail.com',
        ]);

        $teacher = Usuario::factory()->withRole('docente')->create([
            'nombre' => 'Ing. Carlos Docente',
            'correo' => 'carlos.docente@mail.com',
        ]);

        $period = PeriodoAcademico::query()->create([
            'nombre' => 'PAO 2026-1',
            'fecha_inicio' => '2026-05-01',
            'fecha_fin' => '2026-09-30',
            'estado' => true,
        ]);

        $modality = Modalidad::query()->create([
            'nombre' => 'Presencial',
            'estado' => true,
        ]);

        $section = Paralelo::query()->create([
            'nombre' => 'A',
            'estado' => true,
        ]);

        $faculty = Facultad::query()->create([
            'nombre' => 'Facultad de Ingeniería',
            'estado' => true,
        ]);

        $career = Carrera::query()->create([
            'nombre' => 'Ingeniería de Software',
            'fk_facultad' => $faculty->getKey(),
            'estado' => true,
        ]);

        $cycle = Ciclo::query()->create([
            'nombre' => 'Quinto Ciclo',
            'numero' => 5,
            'fk_carrera' => $career->getKey(),
            'estado' => true,
        ]);

        $tutoring = AsignaturaTutoria::query()->create([
            'nombre' => 'Calidad y Pruebas de Software',
            'fk_periodo' => $period->getKey(),
            'fk_modalidad' => $modality->getKey(),
            'fk_paralelo' => $section->getKey(),
            'fk_ciclo' => $cycle->getKey(),
            'fk_docente' => $teacher->getKey(),
            'estado' => true,
        ]);

        $enrollment = InscripcionTutoria::query()->create([
            'fk_asig_tutoria' => $tutoring->getKey(),
            'fk_id_usuario' => $student->getKey(),
            'fecha_inscripcion' => '2026-05-10',
            'estado' => true,
        ]);

        return compact('student', 'teacher', 'period', 'modality', 'section', 'cycle', 'tutoring', 'enrollment');
    }

    public function test_student_can_consult_grades_and_diagnostic_level_for_specific_tutoring(): void
    {
        $setup = $this->createTutoringSetup();
        $student = $setup['student'];
        $tutoring = $setup['tutoring'];
        $enrollment = $setup['enrollment'];

        // Registrar nota diagnóstica
        Nota::query()->create([
            'fk_inscripcion' => $enrollment->getKey(),
            'tipo' => 'diagnostic',
            'valor' => 8.50,
            'fecha_registro' => '2026-05-15',
        ]);

        // Registrar nota parcial
        Nota::query()->create([
            'fk_inscripcion' => $enrollment->getKey(),
            'tipo' => 'partial',
            'valor' => 9.25,
            'fecha_registro' => '2026-06-20',
        ]);

        // Registrar métrica de conocimiento vinculada a la inscripción
        MetricaConocimiento::query()->create([
            'enrollment_id' => $enrollment->getKey(),
            'fk_id_usuario' => $student->getKey(),
            'descripcion' => 'Alto',
            'rango' => 'high',
            'nota_minima' => 7.00,
            'nota_maxima' => 10.00,
            'estado' => true,
        ]);

        Sanctum::actingAs($student, ['access-api']);

        $response = $this->getJson("/api/v1/student/tutoring/{$tutoring->getKey()}/grades");

        $response->assertOk()
            ->assertJsonPath('data.enrollment_id', $enrollment->getKey())
            ->assertJsonPath('data.tutoring.id', $tutoring->getKey())
            ->assertJsonPath('data.tutoring.name', 'Calidad y Pruebas de Software')
            ->assertJsonPath('data.tutoring.teacher.name', 'Ing. Carlos Docente')
            ->assertJsonPath('data.grades.diagnostic.value', 8.5)
            ->assertJsonPath('data.grades.diagnostic.formatted_value', '8.50')
            ->assertJsonPath('data.grades.diagnostic.registered_at', '2026-05-15')
            ->assertJsonPath('data.grades.partial.value', 9.25)
            ->assertJsonPath('data.grades.partial.formatted_value', '9.25')
            ->assertJsonPath('data.grades.partial.registered_at', '2026-06-20')
            ->assertJsonPath('data.knowledge_metric.group', 'Alto')
            ->assertJsonPath('data.knowledge_metric.group_key', 'high')
            ->assertJsonPath('data.knowledge_metric.min_score', 7)
            ->assertJsonPath('data.knowledge_metric.max_score', 10)
            ->assertJsonPath('data.scale_settings.minimum', 0)
            ->assertJsonPath('data.scale_settings.maximum', 10)
            ->assertJsonMissingPath('data.grades.history');
    }

    public function test_student_receives_null_grades_when_teacher_has_not_evaluated_yet(): void
    {
        $setup = $this->createTutoringSetup();
        $student = $setup['student'];
        $tutoring = $setup['tutoring'];

        Sanctum::actingAs($student, ['access-api']);

        $response = $this->getJson("/api/v1/student/tutoring/{$tutoring->getKey()}/grades");

        $response->assertOk()
            ->assertJsonPath('data.grades.diagnostic', null)
            ->assertJsonPath('data.grades.partial', null)
            ->assertJsonPath('data.knowledge_metric', null)
            ->assertJsonMissingPath('data.grades.history');
    }

    public function test_student_can_consult_all_enrolled_tutorings_grades_summary(): void
    {
        $setup = $this->createTutoringSetup();
        $student = $setup['student'];
        $enrollment1 = $setup['enrollment'];

        // Crear una segunda tutoría e inscripción para el mismo estudiante
        $tutoring2 = AsignaturaTutoria::query()->create([
            'nombre' => 'Arquitectura de Software',
            'fk_periodo' => $setup['period']->getKey(),
            'fk_modalidad' => $setup['modality']->getKey(),
            'fk_paralelo' => $setup['section']->getKey(),
            'fk_ciclo' => $setup['cycle']->getKey(),
            'fk_docente' => $setup['teacher']->getKey(),
            'estado' => true,
        ]);

        $enrollment2 = InscripcionTutoria::query()->create([
            'fk_asig_tutoria' => $tutoring2->getKey(),
            'fk_id_usuario' => $student->getKey(),
            'fecha_inscripcion' => '2026-05-12',
            'estado' => true,
        ]);

        // La tutoría 1 tiene nota diagnóstica
        Nota::query()->create([
            'fk_inscripcion' => $enrollment1->getKey(),
            'tipo' => 'diagnostic',
            'valor' => 6.00,
            'fecha_registro' => '2026-05-18',
        ]);
        MetricaConocimiento::query()->create([
            'enrollment_id' => $enrollment1->getKey(),
            'fk_id_usuario' => $student->getKey(),
            'descripcion' => 'Medio',
            'rango' => 'medium',
            'nota_minima' => 4.00,
            'nota_maxima' => 6.99,
            'estado' => true,
        ]);

        Sanctum::actingAs($student, ['access-api']);

        $response = $this->getJson('/api/v1/student/tutoring/grades');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_student_cannot_consult_grades_of_tutoring_they_are_not_enrolled_in(): void
    {
        $setup = $this->createTutoringSetup();
        $tutoring = $setup['tutoring'];

        // Otro estudiante sin inscripción a esa tutoría
        $otherStudent = Usuario::factory()->withRole('estudiante')->create();

        Sanctum::actingAs($otherStudent, ['access-api']);

        $response = $this->getJson("/api/v1/student/tutoring/{$tutoring->getKey()}/grades");

        $response->assertForbidden();
    }

    public function test_student_only_sees_their_own_grades_in_same_tutoring(): void
    {
        $setup = $this->createTutoringSetup();
        $studentA = $setup['student'];
        $enrollmentA = $setup['enrollment'];
        $tutoring = $setup['tutoring'];

        $studentB = Usuario::factory()->withRole('estudiante')->create();
        $enrollmentB = InscripcionTutoria::query()->create([
            'fk_asig_tutoria' => $tutoring->getKey(),
            'fk_id_usuario' => $studentB->getKey(),
            'fecha_inscripcion' => '2026-05-10',
            'estado' => true,
        ]);

        // Nota estudiante A
        Nota::query()->create([
            'fk_inscripcion' => $enrollmentA->getKey(),
            'tipo' => 'diagnostic',
            'valor' => 5.00,
            'fecha_registro' => '2026-05-15',
        ]);
        MetricaConocimiento::query()->create([
            'enrollment_id' => $enrollmentA->getKey(),
            'fk_id_usuario' => $studentA->getKey(),
            'descripcion' => 'Medio',
            'rango' => 'medium',
            'nota_minima' => 4.00,
            'nota_maxima' => 6.99,
            'estado' => true,
        ]);

        // Nota estudiante B
        Nota::query()->create([
            'fk_inscripcion' => $enrollmentB->getKey(),
            'tipo' => 'diagnostic',
            'valor' => 9.50,
            'fecha_registro' => '2026-05-15',
        ]);
        MetricaConocimiento::query()->create([
            'enrollment_id' => $enrollmentB->getKey(),
            'fk_id_usuario' => $studentB->getKey(),
            'descripcion' => 'Alto',
            'rango' => 'high',
            'nota_minima' => 7.00,
            'nota_maxima' => 10.00,
            'estado' => true,
        ]);

        // Estudiante A consulta
        Sanctum::actingAs($studentA, ['access-api']);
        $responseA = $this->getJson("/api/v1/student/tutoring/{$tutoring->getKey()}/grades");
        $responseA->assertOk()
            ->assertJsonPath('data.grades.diagnostic.value', 5)
            ->assertJsonPath('data.knowledge_metric.group', 'Medio');

        // Estudiante B consulta
        Sanctum::actingAs($studentB, ['access-api']);
        $responseB = $this->getJson("/api/v1/student/tutoring/{$tutoring->getKey()}/grades");
        $responseB->assertOk()
            ->assertJsonPath('data.grades.diagnostic.value', 9.5)
            ->assertJsonPath('data.knowledge_metric.group', 'Alto');
    }

    public function test_non_existent_tutoring_returns_404(): void
    {
        $setup = $this->createTutoringSetup();
        $student = $setup['student'];

        Sanctum::actingAs($student, ['access-api']);

        $response = $this->getJson('/api/v1/student/tutoring/999999/grades');

        $response->assertNotFound();
    }

    public function test_unauthenticated_user_cannot_access_student_grades(): void
    {
        $response = $this->getJson('/api/v1/student/tutoring/grades');
        $response->assertUnauthorized();
    }

    public function test_user_without_student_role_cannot_access_student_grades(): void
    {
        $setup = $this->createTutoringSetup();
        $tutoring = $setup['tutoring'];
        $teacher = $setup['teacher'];

        Sanctum::actingAs($teacher, ['access-api']);

        $this->getJson("/api/v1/student/tutoring/{$tutoring->getKey()}/grades")
            ->assertForbidden();

        $this->getJson('/api/v1/student/tutoring/grades')
            ->assertForbidden();
    }
}

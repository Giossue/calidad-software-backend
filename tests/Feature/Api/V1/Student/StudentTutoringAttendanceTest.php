<?php

namespace Tests\Feature\Api\V1\Student;

use App\Models\AsignaturaTutoria;
use App\Models\Asistencia;
use App\Models\Carrera;
use App\Models\Ciclo;
use App\Models\Facultad;
use App\Models\InscripcionTutoria;
use App\Models\Modalidad;
use App\Models\Paralelo;
use App\Models\PeriodoAcademico;
use App\Models\Tema;
use App\Models\TutoringSession;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentTutoringAttendanceTest extends TestCase
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

    public function test_student_can_consult_attendance_record_and_summary_for_specific_tutoring(): void
    {
        $setup = $this->createTutoringSetup();
        $student = $setup['student'];
        $teacher = $setup['teacher'];
        $tutoring = $setup['tutoring'];
        $enrollment = $setup['enrollment'];

        $topic1 = Tema::query()->create([
            'fk_asig_tutoria' => $tutoring->getKey(),
            'nombre' => 'Métricas de Software ISO 25010',
            'descripcion' => 'Estándares de calidad de producto.',
            'estado' => true,
        ]);

        // Sesión 1: Presente con tema abordado
        $session1 = TutoringSession::query()->create([
            'tutoring_id' => $tutoring->getKey(),
            'recorded_by' => $teacher->getKey(),
            'date' => '2026-05-15',
            'topics_covered' => true,
        ]);
        $session1->topics()->attach($topic1->getKey());
        Asistencia::query()->create([
            'fk_inscripcion' => $enrollment->getKey(),
            'fk_id_usuario' => $student->getKey(),
            'session_id' => $session1->getKey(),
            'fecha' => '2026-05-15',
            'estado_asistencia' => true,
        ]);

        // Sesión 2: Presente
        $session2 = TutoringSession::query()->create([
            'tutoring_id' => $tutoring->getKey(),
            'recorded_by' => $teacher->getKey(),
            'date' => '2026-05-22',
            'topics_covered' => false,
        ]);
        Asistencia::query()->create([
            'fk_inscripcion' => $enrollment->getKey(),
            'fk_id_usuario' => $student->getKey(),
            'session_id' => $session2->getKey(),
            'fecha' => '2026-05-22',
            'estado_asistencia' => true,
        ]);

        // Sesión 3: Ausente
        $session3 = TutoringSession::query()->create([
            'tutoring_id' => $tutoring->getKey(),
            'recorded_by' => $teacher->getKey(),
            'date' => '2026-05-29',
            'topics_covered' => false,
        ]);
        Asistencia::query()->create([
            'fk_inscripcion' => $enrollment->getKey(),
            'fk_id_usuario' => $student->getKey(),
            'session_id' => $session3->getKey(),
            'fecha' => '2026-05-29',
            'estado_asistencia' => false,
        ]);

        Sanctum::actingAs($student, ['access-api']);

        $response = $this->getJson("/api/v1/student/tutoring/{$tutoring->getKey()}/attendance");

        $response->assertOk()
            ->assertJsonPath('data.enrollment_id', $enrollment->getKey())
            ->assertJsonPath('data.tutoring.id', $tutoring->getKey())
            ->assertJsonPath('data.tutoring.name', 'Calidad y Pruebas de Software')
            ->assertJsonPath('data.summary.total_sessions', 3)
            ->assertJsonPath('data.summary.present_count', 2)
            ->assertJsonPath('data.summary.absent_count', 1)
            ->assertJsonPath('data.summary.attendance_percentage', 66.67)
            ->assertJsonCount(3, 'data.records')
            ->assertJsonPath('data.records.0.date', '2026-05-29')
            ->assertJsonPath('data.records.0.present', false)
            ->assertJsonPath('data.records.0.status', 'ausente')
            ->assertJsonPath('data.records.2.date', '2026-05-15')
            ->assertJsonPath('data.records.2.present', true)
            ->assertJsonPath('data.records.2.status', 'presente')
            ->assertJsonPath('data.records.2.topics_covered', true)
            ->assertJsonPath('data.records.2.topics.0.name', 'Métricas de Software ISO 25010');
    }

    public function test_student_receives_zero_sessions_summary_when_no_sessions_recorded_yet(): void
    {
        $setup = $this->createTutoringSetup();
        $student = $setup['student'];
        $tutoring = $setup['tutoring'];

        Sanctum::actingAs($student, ['access-api']);

        $response = $this->getJson("/api/v1/student/tutoring/{$tutoring->getKey()}/attendance");

        $response->assertOk()
            ->assertJsonPath('data.summary.total_sessions', 0)
            ->assertJsonPath('data.summary.present_count', 0)
            ->assertJsonPath('data.summary.absent_count', 0)
            ->assertJsonPath('data.summary.attendance_percentage', 0)
            ->assertJsonCount(0, 'data.records');
    }

    public function test_student_can_consult_all_enrolled_tutorings_attendance_summary(): void
    {
        $setup = $this->createTutoringSetup();
        $student = $setup['student'];
        $enrollment1 = $setup['enrollment'];

        $tutoring2 = AsignaturaTutoria::query()->create([
            'nombre' => 'Bases de Datos Avanzadas',
            'fk_periodo' => $setup['period']->getKey(),
            'fk_modalidad' => $setup['modality']->getKey(),
            'fk_paralelo' => $setup['section']->getKey(),
            'fk_ciclo' => $setup['cycle']->getKey(),
            'fk_docente' => $setup['teacher']->getKey(),
            'estado' => true,
        ]);

        InscripcionTutoria::query()->create([
            'fk_asig_tutoria' => $tutoring2->getKey(),
            'fk_id_usuario' => $student->getKey(),
            'fecha_inscripcion' => '2026-05-12',
            'estado' => true,
        ]);

        // Registrar 1 asistencia en la tutoría 1
        Asistencia::query()->create([
            'fk_inscripcion' => $enrollment1->getKey(),
            'fk_id_usuario' => $student->getKey(),
            'fecha' => '2026-05-15',
            'estado_asistencia' => true,
        ]);

        Sanctum::actingAs($student, ['access-api']);

        $response = $this->getJson('/api/v1/student/tutoring/attendance');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_student_cannot_consult_attendance_of_tutoring_they_are_not_enrolled_in(): void
    {
        $setup = $this->createTutoringSetup();
        $tutoring = $setup['tutoring'];

        $otherStudent = Usuario::factory()->withRole('estudiante')->create();

        Sanctum::actingAs($otherStudent, ['access-api']);

        $response = $this->getJson("/api/v1/student/tutoring/{$tutoring->getKey()}/attendance");

        $response->assertForbidden();
    }

    public function test_student_only_sees_their_own_attendance_records_in_same_tutoring(): void
    {
        $setup = $this->createTutoringSetup();
        $studentA = $setup['student'];
        $enrollmentA = $setup['enrollment'];
        $teacher = $setup['teacher'];
        $tutoring = $setup['tutoring'];

        $studentB = Usuario::factory()->withRole('estudiante')->create();
        $enrollmentB = InscripcionTutoria::query()->create([
            'fk_asig_tutoria' => $tutoring->getKey(),
            'fk_id_usuario' => $studentB->getKey(),
            'fecha_inscripcion' => '2026-05-10',
            'estado' => true,
        ]);

        $session = TutoringSession::query()->create([
            'tutoring_id' => $tutoring->getKey(),
            'recorded_by' => $teacher->getKey(),
            'date' => '2026-05-15',
            'topics_covered' => false,
        ]);

        // Estudiante A presente
        Asistencia::query()->create([
            'fk_inscripcion' => $enrollmentA->getKey(),
            'fk_id_usuario' => $studentA->getKey(),
            'session_id' => $session->getKey(),
            'fecha' => '2026-05-15',
            'estado_asistencia' => true,
        ]);

        // Estudiante B ausente
        Asistencia::query()->create([
            'fk_inscripcion' => $enrollmentB->getKey(),
            'fk_id_usuario' => $studentB->getKey(),
            'session_id' => $session->getKey(),
            'fecha' => '2026-05-15',
            'estado_asistencia' => false,
        ]);

        // Estudiante A consulta
        Sanctum::actingAs($studentA, ['access-api']);
        $responseA = $this->getJson("/api/v1/student/tutoring/{$tutoring->getKey()}/attendance");
        $responseA->assertOk()
            ->assertJsonPath('data.summary.present_count', 1)
            ->assertJsonPath('data.summary.absent_count', 0)
            ->assertJsonPath('data.records.0.present', true);

        // Estudiante B consulta
        Sanctum::actingAs($studentB, ['access-api']);
        $responseB = $this->getJson("/api/v1/student/tutoring/{$tutoring->getKey()}/attendance");
        $responseB->assertOk()
            ->assertJsonPath('data.summary.present_count', 0)
            ->assertJsonPath('data.summary.absent_count', 1)
            ->assertJsonPath('data.records.0.present', false);
    }

    public function test_non_existent_tutoring_returns_404(): void
    {
        $setup = $this->createTutoringSetup();
        $student = $setup['student'];

        Sanctum::actingAs($student, ['access-api']);

        $response = $this->getJson('/api/v1/student/tutoring/999999/attendance');

        $response->assertNotFound();
    }

    public function test_unauthenticated_user_cannot_access_student_attendance(): void
    {
        $response = $this->getJson('/api/v1/student/tutoring/attendance');
        $response->assertUnauthorized();
    }

    public function test_user_without_student_role_cannot_access_student_attendance(): void
    {
        $setup = $this->createTutoringSetup();
        $tutoring = $setup['tutoring'];
        $teacher = $setup['teacher'];

        Sanctum::actingAs($teacher, ['access-api']);

        $this->getJson("/api/v1/student/tutoring/{$tutoring->getKey()}/attendance")
            ->assertForbidden();

        $this->getJson('/api/v1/student/tutoring/attendance')
            ->assertForbidden();
    }
}

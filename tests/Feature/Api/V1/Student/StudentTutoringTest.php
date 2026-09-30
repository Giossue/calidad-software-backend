<?php

namespace Tests\Feature\Api\V1\Student;

use App\Models\AsignaturaTutoria;
use App\Models\Ciclo;
use App\Models\Horario;
use App\Models\InscripcionTutoria;
use App\Models\Modalidad;
use App\Models\Paralelo;
use App\Models\PeriodoAcademico;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentTutoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_list_assigned_tutorings_with_subject_teacher_and_schedules(): void
    {
        $student = Usuario::factory()->withRole('estudiante')->create([
            'nombre' => 'María Estudiante',
            'correo' => 'maria.estudiante@mail.com',
        ]);

        $teacher = Usuario::factory()->withRole('docente')->create([
            'nombre' => 'Ing. Roberto Profesor',
            'correo' => 'roberto.docente@mail.com',
            'telefono' => '0981234567',
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

        $faculty = \App\Models\Facultad::query()->create([
            'nombre' => 'Facultad de Ciencias Aplicadas',
            'estado' => true,
        ]);

        $career = \App\Models\Carrera::query()->create([
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

        $subject = AsignaturaTutoria::query()->create([
            'nombre' => 'Ingeniería de Software y Calidad',
            'fk_periodo' => $period->getKey(),
            'fk_modalidad' => $modality->getKey(),
            'fk_paralelo' => $section->getKey(),
            'fk_ciclo' => $cycle->getKey(),
            'fk_docente' => $teacher->getKey(),
            'estado' => true,
        ]);

        $schedule = Horario::query()->create([
            'fk_asig_tutoria' => $subject->getKey(),
            'dia_semana' => 'Martes',
            'hora_inicio' => '10:00:00',
            'hora_fin' => '12:00:00',
            'estado' => true,
        ]);

        $enrollment = InscripcionTutoria::query()->create([
            'fk_asig_tutoria' => $subject->getKey(),
            'fk_id_usuario' => $student->getKey(),
            'fecha_inscripcion' => '2026-05-10',
            'estado' => true,
        ]);

        Sanctum::actingAs($student);

        $response = $this->getJson('/api/v1/student/tutoring');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $enrollment->getKey())
            ->assertJsonPath('data.0.enrolled_at', '2026-05-10')
            ->assertJsonPath('data.0.is_active', true)
            ->assertJsonPath('data.0.subject.id', $subject->getKey())
            ->assertJsonPath('data.0.subject.name', 'Ingeniería de Software y Calidad')
            ->assertJsonPath('data.0.subject.academic_period.name', 'PAO 2026-1')
            ->assertJsonPath('data.0.subject.section.name', 'A')
            ->assertJsonPath('data.0.subject.modality.name', 'Presencial')
            ->assertJsonPath('data.0.subject.cycle.name', 'Quinto Ciclo')
            ->assertJsonPath('data.0.subject.cycle.number', 5)
            ->assertJsonPath('data.0.subject.teacher.id', $teacher->getKey())
            ->assertJsonPath('data.0.subject.teacher.name', 'Ing. Roberto Profesor')
            ->assertJsonPath('data.0.subject.teacher.email', 'roberto.docente@mail.com')
            ->assertJsonPath('data.0.subject.teacher.phone', '0981234567')
            ->assertJsonCount(1, 'data.0.subject.schedules')
            ->assertJsonPath('data.0.subject.schedules.0.id', $schedule->getKey())
            ->assertJsonPath('data.0.subject.schedules.0.day_of_week', 'Martes');
    }

    public function test_student_only_sees_their_own_tutoring_enrollments(): void
    {
        $studentA = Usuario::factory()->withRole('estudiante')->create();
        $studentB = Usuario::factory()->withRole('estudiante')->create();

        $period = PeriodoAcademico::query()->create([
            'nombre' => 'PAO 2026-1',
            'fecha_inicio' => '2026-05-01',
            'fecha_fin' => '2026-09-30',
            'estado' => true,
        ]);

        $subject = AsignaturaTutoria::query()->create([
            'nombre' => 'Bases de Datos Avanzadas',
            'fk_periodo' => $period->getKey(),
            'estado' => true,
        ]);

        InscripcionTutoria::query()->create([
            'fk_asig_tutoria' => $subject->getKey(),
            'fk_id_usuario' => $studentB->getKey(),
            'fecha_inscripcion' => '2026-05-12',
            'estado' => true,
        ]);

        // Estudiante A consulta y no debe ver la inscripción del estudiante B
        Sanctum::actingAs($studentA);
        $responseA = $this->getJson('/api/v1/student/tutoring');
        $responseA->assertOk()->assertJsonCount(0, 'data');

        // Estudiante B consulta y sí ve su inscripción
        Sanctum::actingAs($studentB);
        $responseB = $this->getJson('/api/v1/student/tutoring');
        $responseB->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject.name', 'Bases de Datos Avanzadas');
    }

    public function test_student_without_tutorings_receives_empty_array(): void
    {
        $student = Usuario::factory()->withRole('estudiante')->create();
        Sanctum::actingAs($student);

        $response = $this->getJson('/api/v1/student/tutoring');

        $response->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_unauthenticated_user_cannot_access_student_tutoring(): void
    {
        $this->getJson('/api/v1/student/tutoring')
            ->assertUnauthorized();
    }

    public function test_user_without_student_role_cannot_access_student_tutoring(): void
    {
        $teacher = Usuario::factory()->withRole('docente')->create();
        Sanctum::actingAs($teacher);

        $this->getJson('/api/v1/student/tutoring')
            ->assertForbidden();
    }
}

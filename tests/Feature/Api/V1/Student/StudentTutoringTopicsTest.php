<?php

namespace Tests\Feature\Api\V1\Student;

use App\Models\Actividad;
use App\Models\AsignaturaTutoria;
use App\Models\Ciclo;
use App\Models\InscripcionTutoria;
use App\Models\Metodologia;
use App\Models\Modalidad;
use App\Models\Paralelo;
use App\Models\PeriodoAcademico;
use App\Models\Tema;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentTutoringTopicsTest extends TestCase
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

        $faculty = \App\Models\Facultad::query()->create([
            'nombre' => 'Facultad de Ingeniería',
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

    public function test_student_can_consult_topics_progress_and_syllabus_for_specific_tutoring(): void
    {
        $setup = $this->createTutoringSetup();
        $student = $setup['student'];
        $tutoring = $setup['tutoring'];

        // Tema 1: Abordado (visto = true) con actividad y metodología
        $topic1 = Tema::query()->create([
            'fk_asig_tutoria' => $tutoring->getKey(),
            'nombre' => 'Diseño de Casos de Prueba',
            'descripcion' => 'Técnicas de caja negra y caja blanca.',
            'visto' => true,
            'estado' => true,
        ]);

        $act1 = Actividad::query()->create([
            'fk_tema' => $topic1->getKey(),
            'nombre' => 'Taller de Partición Equivalente',
            'duracion' => '45 minutos',
            'estado' => true,
        ]);

        Metodologia::query()->create([
            'fk_actividad' => $act1->getKey(),
            'descripcion' => 'Resolución de problemas prácticos en grupo.',
            'estado' => true,
        ]);

        // Tema 2: Pendiente (visto = false) con actividad
        $topic2 = Tema::query()->create([
            'fk_asig_tutoria' => $tutoring->getKey(),
            'nombre' => 'Automatización de Pruebas API',
            'descripcion' => 'Validación de endpoints REST.',
            'visto' => false,
            'estado' => true,
        ]);

        Actividad::query()->create([
            'fk_tema' => $topic2->getKey(),
            'nombre' => 'Laboratorio Postman',
            'duracion' => '60 minutos',
            'estado' => true,
        ]);

        // Tema 3: Inactivo (dado de baja lógica por el docente)
        Tema::query()->create([
            'fk_asig_tutoria' => $tutoring->getKey(),
            'nombre' => 'Tema Deshabilitado',
            'descripcion' => 'Contenido obsoleto.',
            'visto' => false,
            'estado' => false,
        ]);

        Sanctum::actingAs($student, ['access-api']);

        $response = $this->getJson("/api/v1/student/tutoring/{$tutoring->getKey()}/topics");

        $response->assertOk()
            ->assertJsonPath('data.tutoring.id', $tutoring->getKey())
            ->assertJsonPath('data.tutoring.name', 'Calidad y Pruebas de Software')
            ->assertJsonPath('data.tutoring.teacher.name', 'Ing. Carlos Docente')
            ->assertJsonPath('data.progress.total_topics', 2)
            ->assertJsonPath('data.progress.covered_topics', 1)
            ->assertJsonPath('data.progress.pending_topics', 1)
            ->assertJsonPath('data.progress.progress_percentage', 50)
            ->assertJsonCount(2, 'data.topics')
            ->assertJsonPath('data.topics.0.name', 'Diseño de Casos de Prueba')
            ->assertJsonPath('data.topics.0.is_covered', true)
            ->assertJsonPath('data.topics.0.activities.0.name', 'Taller de Partición Equivalente')
            ->assertJsonPath('data.topics.0.activities.0.duration', '45 minutos')
            ->assertJsonPath('data.topics.0.activities.0.methodologies.0.description', 'Resolución de problemas prácticos en grupo.')
            ->assertJsonPath('data.topics.1.name', 'Automatización de Pruebas API')
            ->assertJsonPath('data.topics.1.is_covered', false);
    }

    public function test_student_can_consult_topic_detail_individually(): void
    {
        $setup = $this->createTutoringSetup();
        $student = $setup['student'];
        $tutoring = $setup['tutoring'];

        $topic = Tema::query()->create([
            'fk_asig_tutoria' => $tutoring->getKey(),
            'nombre' => 'Diseño de Casos de Prueba',
            'descripcion' => 'Técnicas de caja negra.',
            'visto' => true,
            'estado' => true,
        ]);

        $activity = Actividad::query()->create([
            'fk_tema' => $topic->getKey(),
            'nombre' => 'Taller de Partición Equivalente',
            'duracion' => '45 minutos',
            'estado' => true,
        ]);

        Metodologia::query()->create([
            'fk_actividad' => $activity->getKey(),
            'descripcion' => 'Resolución práctica.',
            'estado' => true,
        ]);

        Sanctum::actingAs($student, ['access-api']);

        $response = $this->getJson("/api/v1/student/tutoring/{$tutoring->getKey()}/topics/{$topic->getKey()}");

        $response->assertOk()
            ->assertJsonPath('data.id', $topic->getKey())
            ->assertJsonPath('data.name', 'Diseño de Casos de Prueba')
            ->assertJsonPath('data.is_covered', true)
            ->assertJsonCount(1, 'data.activities')
            ->assertJsonPath('data.activities.0.name', 'Taller de Partición Equivalente')
            ->assertJsonCount(1, 'data.activities.0.methodologies');
    }

    public function test_student_receives_empty_topics_and_zero_progress_when_no_topics_configured(): void
    {
        $setup = $this->createTutoringSetup();
        $student = $setup['student'];
        $tutoring = $setup['tutoring'];

        Sanctum::actingAs($student, ['access-api']);

        $response = $this->getJson("/api/v1/student/tutoring/{$tutoring->getKey()}/topics");

        $response->assertOk()
            ->assertJsonPath('data.progress.total_topics', 0)
            ->assertJsonPath('data.progress.covered_topics', 0)
            ->assertJsonPath('data.progress.pending_topics', 0)
            ->assertJsonPath('data.progress.progress_percentage', 0)
            ->assertJsonCount(0, 'data.topics');
    }

    public function test_student_cannot_consult_topics_of_tutoring_they_are_not_enrolled_in(): void
    {
        $setup = $this->createTutoringSetup();
        $tutoring = $setup['tutoring'];

        $otherStudent = Usuario::factory()->withRole('estudiante')->create();

        Sanctum::actingAs($otherStudent, ['access-api']);

        $response = $this->getJson("/api/v1/student/tutoring/{$tutoring->getKey()}/topics");

        $response->assertForbidden();
    }

    public function test_student_cannot_consult_topic_detail_if_topic_belongs_to_another_tutoring(): void
    {
        $setup = $this->createTutoringSetup();
        $student = $setup['student'];
        $tutoringA = $setup['tutoring'];

        // Otra tutoría B
        $tutoringB = AsignaturaTutoria::query()->create([
            'nombre' => 'Bases de Datos',
            'fk_periodo' => $setup['period']->getKey(),
            'fk_modalidad' => $setup['modality']->getKey(),
            'fk_paralelo' => $setup['section']->getKey(),
            'fk_ciclo' => $setup['cycle']->getKey(),
            'fk_docente' => $setup['teacher']->getKey(),
            'estado' => true,
        ]);

        $topicB = Tema::query()->create([
            'fk_asig_tutoria' => $tutoringB->getKey(),
            'nombre' => 'Normalización',
            'estado' => true,
        ]);

        Sanctum::actingAs($student, ['access-api']);

        // El estudiante está inscrito en A, pero busca el tema de B en la ruta de A
        $response = $this->getJson("/api/v1/student/tutoring/{$tutoringA->getKey()}/topics/{$topicB->getKey()}");

        $response->assertNotFound();
    }

    public function test_non_existent_tutoring_returns_404(): void
    {
        $setup = $this->createTutoringSetup();
        $student = $setup['student'];

        Sanctum::actingAs($student, ['access-api']);

        $response = $this->getJson('/api/v1/student/tutoring/999999/topics');

        $response->assertNotFound();
    }

    public function test_unauthenticated_user_cannot_access_topics(): void
    {
        $setup = $this->createTutoringSetup();
        $tutoring = $setup['tutoring'];

        $response = $this->getJson("/api/v1/student/tutoring/{$tutoring->getKey()}/topics");

        $response->assertUnauthorized();
    }

    public function test_user_without_student_role_cannot_access_student_topics(): void
    {
        $setup = $this->createTutoringSetup();
        $tutoring = $setup['tutoring'];
        $teacher = $setup['teacher'];

        Sanctum::actingAs($teacher, ['access-api']);

        $response = $this->getJson("/api/v1/student/tutoring/{$tutoring->getKey()}/topics");

        $response->assertForbidden();
    }
}

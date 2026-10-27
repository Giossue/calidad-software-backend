<?php

namespace Tests\Feature\Api\V1\Tutoring;

use App\Models\Usuario;
use App\Notifications\ProvisionalPasswordNotification;
use Illuminate\Support\Facades\Notification;

class CoordinatorStudentTest extends TutoringTestCase
{
    public function test_coordinator_can_list_students(): void
    {
        $student = Usuario::factory()->withRole('estudiante')->create(['nombre' => 'Ana Estudiante', 'fk_carrera' => $this->career->getKey()]);

        $response = $this->actingAs($this->coordinator)->getJson(self::API.'/students');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $student->getKey())
            ->assertJsonPath('data.0.name', 'Ana Estudiante');
    }

    public function test_coordinator_can_filter_students_by_career_cycle_and_tutoring_status(): void
    {
        $withTutoring = Usuario::factory()->withRole('estudiante')->create(['nombre' => 'Con Tutoria', 'fk_carrera' => $this->career->getKey()]);
        Usuario::factory()->withRole('estudiante')->create(['nombre' => 'Sin Tutoria', 'fk_carrera' => $this->career->getKey()]);
        $tutoring = $this->createTutoring($this->cycle);
        $this->actingAs($this->coordinator)->postJson(self::API."/students/{$withTutoring->getKey()}/enroll", ['tutoring_id' => $tutoring->getKey()])->assertCreated();

        $base = self::API.'/students?career_id='.$this->career->getKey();

        $this->actingAs($this->coordinator)->getJson($base.'&tutoring_status=with')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'Con Tutoria');
        $this->actingAs($this->coordinator)->getJson($base.'&tutoring_status=without')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'Sin Tutoria');
        $this->actingAs($this->coordinator)->getJson($base.'&cycle_number='.$this->cycle->numero)
            ->assertOk()->assertJsonPath('data.0.name', 'Con Tutoria')->assertJsonPath('meta.from', 1);
        $this->actingAs($this->coordinator)->getJson($base.'&cycle_number='.($this->cycle->numero + 5))
            ->assertOk()->assertJsonPath('meta.total', 0);
        $this->actingAs($this->coordinator)->getJson(self::API.'/students?career_id=999999')->assertForbidden();
    }

    public function test_coordinator_can_create_a_student_and_assign_initial_tutoring(): void
    {
        Notification::fake();

        // El último ciclo de la carrera es titulación y no admite tutorías.
        $this->createCycle($this->career, null, 2);
        $tutoring = $this->createTutoring($this->cycle);

        $response = $this->actingAs($this->coordinator)->postJson(self::API.'/students', [
            'identification' => '0926687856',
            'name' => 'Carlos Estudiante Nuevo',
            'email' => 'carlos.nuevo@ueb.edu.ec',
            'phone' => '0981112233',
            'cycle_id' => $this->cycle->getKey(),
            'tutoring_id' => $tutoring->getKey(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Carlos Estudiante Nuevo')
            ->assertJsonPath('data.cycle_id', $this->cycle->getKey())
            ->assertJsonPath('data.cycle_number', $this->cycle->numero)
            ->assertJsonPath('data.tutoring_count', 1);

        $this->assertDatabaseHas('usuario', [
            'cedula' => '0926687856',
            'correo' => 'carlos.nuevo@ueb.edu.ec',
            'fk_ciclo' => $this->cycle->getKey(),
            'fk_carrera' => $this->career->getKey(),
        ]);

        Notification::assertSentTo(
            Usuario::query()->where('cedula', '0926687856')->first(),
            ProvisionalPasswordNotification::class
        );
    }

    public function test_coordinator_can_enroll_and_unenroll_student_in_tutoring(): void
    {
        $student = Usuario::factory()->withRole('estudiante')->create(['fk_carrera' => $this->career->getKey()]);
        $tutoring = $this->createTutoring($this->cycle);

        // List available
        $available = $this->actingAs($this->coordinator)->getJson(self::API."/students/{$student->getKey()}/available-tutorings");
        $available->assertOk()->assertJsonCount(1, 'data');

        // Enroll
        $enroll = $this->actingAs($this->coordinator)->postJson(self::API."/students/{$student->getKey()}/enroll", [
            'tutoring_id' => $tutoring->getKey(),
        ]);
        $enroll->assertCreated();

        $this->assertDatabaseHas('inscripcion_tutoria', [
            'fk_id_usuario' => $student->getKey(),
            'fk_asig_tutoria' => $tutoring->getKey(),
            'estado' => true,
        ]);

        $enrollmentId = $enroll->json('data.id');

        // Unenroll
        $unenroll = $this->actingAs($this->coordinator)->deleteJson(self::API."/students/{$student->getKey()}/enrollments/{$enrollmentId}");
        $unenroll->assertOk();

        $this->assertDatabaseHas('inscripcion_tutoria', [
            'id_inscripcion' => $enrollmentId,
            'estado' => false,
        ]);
    }

    public function test_students_are_listed_by_their_registered_cycle_not_by_a_shared_section(): void
    {
        // El paralelo "A" es compartido por ciclos de varias carreras; no debe mezclar listas.
        $section = $this->cycle->paralelo;
        $second = $this->createCycle($this->career, $section, 2);
        $first = Usuario::factory()->withRole('estudiante')->create(['nombre' => 'Primer Ciclo', 'fk_carrera' => $this->career->getKey(), 'fk_ciclo' => $this->cycle->getKey()]);
        $first->paralelos()->attach($section, ['fecha_asignacion' => today(), 'estado' => true]);
        Usuario::factory()->withRole('estudiante')->create(['nombre' => 'Segundo Ciclo', 'fk_carrera' => $this->career->getKey(), 'fk_ciclo' => $second->getKey()]);
        $foreign = Usuario::factory()->withRole('estudiante')->create(['nombre' => 'Otra Carrera', 'fk_carrera' => $this->otherCareer->getKey(), 'fk_ciclo' => $this->otherCycle->getKey()]);
        $foreign->paralelos()->attach($section, ['fecha_asignacion' => today(), 'estado' => true]);
        Usuario::factory()->withRole('estudiante')->create(['nombre' => 'Sin Ciclo', 'fk_carrera' => $this->career->getKey()]);

        $base = self::API.'/students?career_id='.$this->career->getKey();
        $this->actingAs($this->coordinator)->getJson($base.'&cycle_number=1')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'Primer Ciclo')
            ->assertJsonPath('data.0.cycle_number', 1)->assertJsonPath('data.0.section_name', 'A')
            ->assertJsonPath('data.0.academic_stage', 'tutorias');
        $this->actingAs($this->coordinator)->getJson($base.'&cycle_number=2')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'Segundo Ciclo')
            ->assertJsonPath('data.0.academic_stage', 'titulacion');
        $this->actingAs($this->coordinator)->getJson($base.'&without_cycle=1')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'Sin Ciclo');
        $this->actingAs($this->coordinator)->getJson($base)->assertOk()->assertJsonPath('meta.total', 3);
    }

    public function test_a_new_student_needs_a_cycle_of_the_coordinated_career_and_a_tutoring_of_that_cycle(): void
    {
        $payload = ['identification' => '0926687856', 'name' => 'Carlos Nuevo', 'email' => 'carlos.nuevo@ueb.edu.ec'];
        $otherCycleTutoring = $this->createTutoring($this->createCycle($this->career, null, 2));

        $this->actingAs($this->coordinator)->postJson(self::API.'/students', $payload)->assertUnprocessable()->assertJsonValidationErrors('cycle_id');
        $this->actingAs($this->coordinator)->postJson(self::API.'/students', [...$payload, 'cycle_id' => $this->otherCycle->getKey()])->assertForbidden();
        $this->actingAs($this->coordinator)->postJson(self::API.'/students', [...$payload, 'cycle_id' => $this->cycle->getKey(), 'tutoring_id' => $otherCycleTutoring->getKey()])
            ->assertUnprocessable()->assertJsonValidationErrors('tutoring_id');
        $this->assertDatabaseMissing('usuario', ['cedula' => '0926687856']);

        $this->actingAs($this->coordinator)->postJson(self::API.'/students', [...$payload, 'cycle_id' => $this->cycle->getKey()])->assertCreated()
            ->assertJsonPath('data.cycle_id', $this->cycle->getKey())->assertJsonPath('data.tutoring_count', 0);
        // Queda en el paralelo del ciclo, que usan los docentes para inscribir estudiantes.
        $student = Usuario::query()->where('cedula', '0926687856')->firstOrFail();
        $this->assertTrue($student->paralelos()->whereKey($this->cycle->fk_paralelo)->exists());
    }

    public function test_coordinator_assigns_or_changes_the_cycle_of_a_student(): void
    {
        $student = Usuario::factory()->withRole('estudiante')->create(['fk_carrera' => $this->career->getKey()]);
        $second = $this->createCycle($this->career, null, 2);
        $path = self::API."/students/{$student->getKey()}/cycle";

        $this->actingAs($this->coordinator)->patchJson($path, ['cycle_id' => $this->cycle->getKey()])->assertOk()->assertJsonPath('data.cycle_number', 1);
        $this->actingAs($this->coordinator)->patchJson($path, ['cycle_id' => $second->getKey()])->assertOk()
            ->assertJsonPath('data.cycle_number', 2)->assertJsonPath('data.academic_stage', 'titulacion');
        $this->assertDatabaseHas('usuario', ['id_usuario' => $student->getKey(), 'fk_ciclo' => $second->getKey()]);

        $this->actingAs($this->coordinator)->patchJson($path, ['cycle_id' => $this->otherCycle->getKey()])->assertForbidden();
        $foreign = Usuario::factory()->withRole('estudiante')->create(['fk_carrera' => $this->otherCareer->getKey()]);
        $this->actingAs($this->coordinator)->patchJson(self::API."/students/{$foreign->getKey()}/cycle", ['cycle_id' => $this->cycle->getKey()])->assertForbidden();
        $teacher = Usuario::factory()->withRole('docente')->create(['fk_carrera' => $this->career->getKey()]);
        $this->actingAs($this->coordinator)->patchJson(self::API."/students/{$teacher->getKey()}/cycle", ['cycle_id' => $this->cycle->getKey()])->assertForbidden();
    }

    public function test_degree_students_can_be_filtered_by_cycle(): void
    {
        $degreeCycle = $this->createCycle($this->career, null, 2);
        Usuario::factory()->withRole('estudiante')->create(['nombre' => 'En Titulacion', 'fk_carrera' => $this->career->getKey(), 'fk_ciclo' => $degreeCycle->getKey()]);
        Usuario::factory()->withRole('estudiante')->create(['nombre' => 'En Tutorias', 'fk_carrera' => $this->career->getKey(), 'fk_ciclo' => $this->cycle->getKey()]);

        $this->actingAs($this->coordinator)->getJson('/api/v1/tutoring-coordination/degree-students?career_id='.$this->career->getKey().'&cycle_number=2')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'En Titulacion')
            ->assertJsonPath('data.0.academic_stage', 'titulacion');
    }
}

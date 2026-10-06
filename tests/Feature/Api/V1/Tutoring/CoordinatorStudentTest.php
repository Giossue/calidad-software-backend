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

        $tutoring = $this->createTutoring($this->cycle);
        // Con un segundo ciclo, el primero corresponde a tutorías.
        $this->createCycle($this->career, number: 2);

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
            ->assertJsonPath('data.tutoring_count', 1);

        $this->assertDatabaseHas('usuario', [
            'cedula' => '0926687856',
            'correo' => 'carlos.nuevo@ueb.edu.ec',
            'fk_carrera' => $this->career->getKey(),
            'ciclo_actual' => $this->cycle->numero,
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
}

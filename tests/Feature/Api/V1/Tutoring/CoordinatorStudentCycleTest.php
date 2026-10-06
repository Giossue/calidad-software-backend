<?php

namespace Tests\Feature\Api\V1\Tutoring;

use App\Models\Ciclo;
use App\Models\Usuario;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

class CoordinatorStudentCycleTest extends TutoringTestCase
{
    private Ciclo $lastCycle;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        // La carrera queda con dos ciclos: el segundo es titulación.
        $this->lastCycle = $this->createCycle($this->career, number: 2);
        Sanctum::actingAs($this->coordinator, ['*']);
    }

    public function test_coordinator_registers_the_student_cycle_and_cannot_give_tutorings_in_the_degree_cycle(): void
    {
        $payload = ['identification' => '0926687856', 'name' => 'Carlos Nuevo', 'email' => 'carlos.nuevo@ueb.edu.ec'];

        $this->postJson(self::API.'/students', $payload)->assertUnprocessable()->assertJsonValidationErrors('cycle_id');
        $this->postJson(self::API.'/students', [...$payload, 'cycle_id' => $this->otherCycle->getKey()])->assertForbidden();

        // Con una carrera elegida, el ciclo debe pertenecer a ella.
        $this->coordinator->coordinatedCareers()->attach($this->otherCareer);
        $this->postJson(self::API.'/students', [...$payload, 'career_id' => $this->career->getKey(), 'cycle_id' => $this->otherCycle->getKey()])
            ->assertUnprocessable()->assertJsonValidationErrors('cycle_id');
        $this->coordinator->coordinatedCareers()->detach($this->otherCareer);

        $tutoring = $this->createTutoring($this->cycle);
        $this->postJson(self::API.'/students', [...$payload, 'cycle_id' => $this->lastCycle->getKey(), 'tutoring_id' => $tutoring->getKey()])
            ->assertUnprocessable()->assertJsonValidationErrors('tutoring_id');
        $this->assertDatabaseMissing('usuario', ['cedula' => '0926687856']);

        $this->postJson(self::API.'/students', [...$payload, 'cycle_id' => $this->lastCycle->getKey()])->assertCreated();
        $student = Usuario::query()->where('cedula', '0926687856')->firstOrFail();
        $this->assertSame(2, $student->ciclo_actual);
        $this->assertSame('titulacion', $student->academicStage());
    }

    public function test_degree_cycle_students_cannot_be_enrolled_in_tutorings(): void
    {
        $tutoring = $this->createTutoring($this->cycle);
        $student = Usuario::factory()->withRole('estudiante')->create([
            'fk_carrera' => $this->career->getKey(), 'ciclo_actual' => 2,
        ]);
        $student->paralelos()->attach($this->cycle->fk_paralelo, ['fecha_asignacion' => today(), 'estado' => true]);

        $this->postJson(self::API."/students/{$student->getKey()}/enroll", ['tutoring_id' => $tutoring->getKey()])
            ->assertUnprocessable()->assertJsonValidationErrors('tutoring_id');
        $this->postJson(self::API."/tutorings/{$tutoring->getKey()}/students", ['student_id' => $student->getKey()])
            ->assertUnprocessable()->assertJsonValidationErrors('student_id');
        $this->assertDatabaseCount('inscripcion_tutoria', 0);
    }

    public function test_new_student_registered_from_a_tutoring_takes_its_career_and_cycle(): void
    {
        $tutoring = $this->createTutoring($this->cycle);

        $this->postJson(self::API."/tutorings/{$tutoring->getKey()}/students", [
            'identification' => '0926687856', 'name' => 'Ana Nueva', 'email' => 'ana.nueva@ueb.edu.ec',
        ])->assertCreated();

        $this->assertDatabaseHas('usuario', [
            'cedula' => '0926687856', 'fk_carrera' => $this->career->getKey(), 'ciclo_actual' => 1,
        ]);
    }

    public function test_only_degree_cycle_students_are_enrolled_in_degree(): void
    {
        $tutoringStudent = Usuario::factory()->withRole('estudiante')->create(['fk_carrera' => $this->career->getKey(), 'ciclo_actual' => 1]);
        $degreeStudent = Usuario::factory()->withRole('estudiante')->create(['fk_carrera' => $this->career->getKey(), 'ciclo_actual' => 2]);

        $this->postJson(self::API."/degree-students/{$tutoringStudent->getKey()}/enroll")->assertUnprocessable();
        $this->postJson(self::API."/degree-students/{$degreeStudent->getKey()}/enroll")->assertOk();

        $this->getJson(self::API.'/degree-students?search='.$degreeStudent->cedula)->assertOk()
            ->assertJsonPath('data.0.cycle_number', 2)->assertJsonPath('data.0.academic_stage', 'titulacion');
    }
}

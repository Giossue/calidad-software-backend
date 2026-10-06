<?php

namespace Tests\Feature\Api\V1\Student;

use App\Models\Carrera;
use App\Models\Ciclo;
use App\Models\Facultad;
use App\Models\Modalidad;
use App\Models\Paralelo;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentAcademicStageTest extends TestCase
{
    use RefreshDatabase;

    private Carrera $career;

    protected function setUp(): void
    {
        parent::setUp();

        $modality = Modalidad::query()->create(['nombre' => 'Presencial', 'estado' => true]);
        $faculty = Facultad::query()->create(['nombre' => 'Ingeniería', 'estado' => true]);
        $this->career = Carrera::query()->create([
            'fk_facultad' => $faculty->getKey(), 'fk_modalidad' => $modality->getKey(), 'nombre' => 'Software', 'estado' => true,
        ]);
        $section = Paralelo::query()->create(['nombre' => 'A', 'estado' => true]);
        foreach (range(1, 8) as $number) {
            Ciclo::query()->create([
                'fk_carrera' => $this->career->getKey(), 'fk_paralelo' => $section->getKey(),
                'nombre' => "Ciclo {$number}", 'numero' => $number, 'estado' => true,
            ]);
        }
    }

    public function test_last_cycle_students_only_access_degree_and_earlier_cycles_only_tutoring(): void
    {
        $seventh = $this->student(7);
        Sanctum::actingAs($seventh, ['*']);
        $this->getJson('/api/v1/auth/user')->assertOk()
            ->assertJsonPath('data.cycle_number', 7)->assertJsonPath('data.academic_stage', 'tutorias');
        $this->getJson('/api/v1/student/tutoring')->assertOk();
        $this->getJson('/api/v1/student/degree-topics')->assertForbidden();

        $eighth = $this->student(8);
        Sanctum::actingAs($eighth, ['*']);
        $this->getJson('/api/v1/auth/user')->assertOk()->assertJsonPath('data.academic_stage', 'titulacion');
        $this->getJson('/api/v1/student/degree-topics')->assertOk();
        $this->getJson('/api/v1/student/tutoring')->assertForbidden();
    }

    public function test_students_without_a_registered_cycle_keep_their_previous_access(): void
    {
        $student = Usuario::factory()->withRole('estudiante')->create(['fk_carrera' => $this->career->getKey()]);
        Sanctum::actingAs($student, ['*']);

        $this->getJson('/api/v1/auth/user')->assertOk()->assertJsonPath('data.academic_stage', null);
        $this->getJson('/api/v1/student/tutoring')->assertOk();
        $this->getJson('/api/v1/student/degree-topics')->assertOk();
    }

    public function test_administrator_registers_the_student_cycle_within_the_career(): void
    {
        Notification::fake();
        Sanctum::actingAs(Usuario::factory()->withRole('administrador')->create(), ['*']);
        $payload = [
            'identification' => '0926687856', 'name' => 'Luis Pérez', 'email' => 'luis@example.com',
            'phone' => '0991234567', 'role' => 'estudiante', 'career_id' => $this->career->getKey(),
        ];

        $this->postJson('/api/v1/users', $payload)->assertUnprocessable()->assertJsonValidationErrors('cycle_number');
        $this->postJson('/api/v1/users', [...$payload, 'cycle_number' => 9])->assertUnprocessable()->assertJsonValidationErrors('cycle_number');

        $id = $this->postJson('/api/v1/users', [...$payload, 'cycle_number' => 8])->assertCreated()
            ->assertJsonPath('data.cycle_number', 8)->assertJsonPath('data.academic_stage', 'titulacion')->json('data.id');

        $this->patchJson("/api/v1/users/{$id}", ['role' => 'estudiante', 'cycle_number' => 3])->assertOk()
            ->assertJsonPath('data.cycle_number', 3)->assertJsonPath('data.academic_stage', 'tutorias');

        // Otros roles no tienen ciclo.
        $this->patchJson("/api/v1/users/{$id}", ['role' => 'docente'])->assertOk()->assertJsonPath('data.cycle_number', null);
    }

    private function student(int $cycle): Usuario
    {
        return Usuario::factory()->withRole('estudiante')->create([
            'fk_carrera' => $this->career->getKey(),
            'ciclo_actual' => $cycle,
        ]);
    }
}

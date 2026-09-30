<?php

namespace Tests\Feature\Api\V1\Teacher;

use App\Models\Asistencia;
use App\Models\MetricaConocimiento;
use App\Models\Paralelo;
use App\Models\Role;
use App\Models\Usuario;

class EnrollmentAndGradeTest extends TeacherTestCase
{
    public function test_a_new_student_gets_an_account_parallel_and_enrollment_without_exposed_secrets(): void
    {
        $draft = Usuario::factory()->make();
        $response = $this->postJson($this->path('/students'), ['identification' => $draft->cedula, 'name' => 'Ana Pérez', 'email' => 'ana.perez@ueb.edu.ec', 'phone' => '0991234567']);
        $response->assertCreated()->assertJsonPath('data.name', 'Ana Pérez')->assertJsonPath('data.is_active', true)->assertJsonMissingPath('data.password')->assertJsonMissingPath('data.password_hash');
        $student = Usuario::query()->findOrFail($response->json('data.student_id'));
        $this->assertTrue($student->hasRole('estudiante'));
        $this->assertTrue($student->paralelos()->whereKey($this->tutoring->fk_paralelo)->wherePivot('estado', true)->exists());
        $this->assertDatabaseCount('inscripcion_tutoria', 1);
    }

    public function test_existing_student_selection_is_limited_to_the_parallel_and_role(): void
    {
        $student = Usuario::factory()->withRole('estudiante')->create();
        $section = Paralelo::query()->create(['nombre' => 'Z', 'estado' => true]);
        $student->paralelos()->attach($section, ['fecha_asignacion' => today(), 'estado' => true]);
        $this->postJson($this->path('/students'), ['student_id' => $student->getKey()])->assertUnprocessable()->assertJsonValidationErrors('student_id');
        $this->postJson($this->path('/students'), ['student_id' => $this->teacher->getKey()])->assertUnprocessable();
        $this->getJson($this->path('/available-students'))->assertOk()->assertJsonCount(0, 'data');
        $student->paralelos()->attach($this->tutoring->fk_paralelo, ['fecha_asignacion' => today(), 'estado' => true]);
        $this->getJson($this->path('/available-students').'?search='.$student->cedula)->assertOk()->assertJsonPath('data.0.id', $student->getKey());
        $this->postJson($this->path('/students'), ['student_id' => $student->getKey()])->assertCreated();
        $this->postJson($this->path('/students'), ['student_id' => $student->getKey()])->assertUnprocessable();
        $this->assertDatabaseCount('inscripcion_tutoria', 1);
    }

    public function test_contact_updates_preserve_identity_and_account_permissions(): void
    {
        $enrollment = $this->enrollment();
        $this->patchJson($this->path('/students/'.$enrollment->getKey()), ['name' => 'María López', 'phone' => '0987654321'])->assertOk()->assertJsonPath('data.name', 'María López');
        $this->patchJson($this->path('/students/'.$enrollment->getKey()), ['name' => 'María López', 'email' => 'otra@ueb.edu.ec'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->patchJson($this->path('/students/'.$enrollment->getKey()), ['name' => 'María 123'])->assertUnprocessable();
        $this->assertTrue($enrollment->estudiante->refresh()->hasRole('estudiante'));
    }

    public function test_deactivation_and_reenrollment_keep_the_account_grades_and_attendance(): void
    {
        $enrollment = $this->enrollment();
        $this->putJson($this->path('/students/'.$enrollment->getKey().'/grades/diagnostic'), ['value' => 8])->assertOk();
        Asistencia::query()->create(['fk_inscripcion' => $enrollment->getKey(), 'fk_id_usuario' => $enrollment->fk_id_usuario, 'fecha' => today(), 'estado_asistencia' => true]);
        $this->patchJson($this->path('/students/'.$enrollment->getKey().'/deactivate'))->assertOk()->assertJsonPath('data.is_active', false);
        $this->assertTrue($enrollment->estudiante->refresh()->estado);
        $this->assertDatabaseCount('nota', 1);
        $this->assertDatabaseCount('asistencia', 1);
        $this->putJson($this->path('/students/'.$enrollment->getKey().'/grades/partial'), ['value' => 8])->assertUnprocessable();
        $this->postJson($this->path('/students'), ['student_id' => $enrollment->fk_id_usuario])->assertCreated()->assertJsonPath('data.id', $enrollment->getKey())->assertJsonPath('data.diagnostic_grade', '8.00');
        $this->assertDatabaseCount('inscripcion_tutoria', 1);
    }

    public function test_diagnostic_boundaries_classify_and_a_correction_replaces_the_grade(): void
    {
        $enrollment = $this->enrollment();
        foreach ([[0, 'Bajo'], [3.99, 'Bajo'], [4, 'Medio'], [6.99, 'Medio'], [7, 'Alto'], [10, 'Alto']] as [$value, $group]) {
            $this->putJson($this->path('/students/'.$enrollment->getKey().'/grades/diagnostic'), ['value' => $value])->assertOk()->assertJsonPath('data.knowledge_group', $group);
        }
        // Una sola nota por etapa: las correcciones reemplazan el valor, no crean historial.
        $this->assertDatabaseCount('nota', 1);
        $this->assertDatabaseHas('nota', ['tipo' => 'diagnostic', 'valor' => '10.00']);
        $this->assertDatabaseCount('metrica_conocimiento', 1);
        $this->putJson($this->path('/students/'.$enrollment->getKey().'/grades/diagnostic'), ['value' => '10.00'])->assertOk();
        $this->assertDatabaseCount('nota', 1);
        $this->putJson($this->path('/students/'.$enrollment->getKey().'/grades/partial'), ['value' => 5])->assertOk()->assertJsonPath('data.partial_grade', '5.00')->assertJsonPath('data.knowledge_group', 'Alto');
    }

    public function test_grade_validation_and_cross_tutoring_enrollments(): void
    {
        $enrollment = $this->enrollment();
        foreach ([-0.01, 10.01, '7.123', 'abc'] as $value) {
            $this->putJson($this->path('/students/'.$enrollment->getKey().'/grades/diagnostic'), ['value' => $value])->assertUnprocessable()->assertJsonValidationErrors('value');
        }
        $this->putJson($this->path('/students/'.$enrollment->getKey().'/grades/final'), ['value' => 8])->assertNotFound();
        $foreign = $this->enrollment($this->createTutoring($this->otherCycle));
        $this->patchJson($this->path('/students/'.$foreign->getKey()), ['name' => 'Ana Pérez'])->assertNotFound();
        $this->putJson($this->path('/students/'.$foreign->getKey().'/grades/partial'), ['value' => 8])->assertNotFound();
        $this->assertDatabaseCount('nota', 0);
    }

    public function test_knowledge_groups_are_per_enrollment_and_use_server_configuration(): void
    {
        config(['teaching.grade_max' => 20, 'teaching.groups' => [['key' => 'advanced', 'label' => 'Avanzado', 'min' => 0, 'max' => 20]]]);
        $enrollment = $this->enrollment();
        $other = $this->createTutoring($this->otherCycle);
        $other->update(['fk_docente' => $this->teacher->getKey()]);
        $second = $this->enrollment($other, $enrollment->estudiante);
        $this->getJson('/api/v1/teacher/grade-settings')->assertOk()->assertJsonPath('data.maximum', 20);
        $this->putJson($this->path('/students/'.$enrollment->getKey().'/grades/diagnostic'), ['value' => 15])->assertOk()->assertJsonPath('data.knowledge_group', 'Avanzado');
        $this->assertNull($second->refresh()->knowledgeMetric);
        $this->assertSame($enrollment->getKey(), MetricaConocimiento::query()->first()->enrollment_id);
    }

    public function test_inactive_accounts_and_multirole_identity_updates_are_denied(): void
    {
        $enrollment = $this->enrollment();
        $enrollment->estudiante->update(['estado' => false]);
        $this->putJson($this->path('/students/'.$enrollment->getKey().'/grades/diagnostic'), ['value' => 8])->assertUnprocessable();
        $enrollment->estudiante->roles()->attach(Role::query()->where('slug', 'administrador')->first());
        $this->patchJson($this->path('/students/'.$enrollment->getKey()), ['name' => 'Cuenta modificada'])->assertForbidden();
        $this->assertDatabaseCount('nota', 0);
    }

    public function test_grades_for_many_students_are_saved_atomically_with_a_second_partial(): void
    {
        $first = $this->enrollment();
        $second = $this->enrollment();
        $payload = ['grades' => [
            ['enrollment_id' => $first->getKey(), 'diagnostic' => '8.25', 'partial' => '9.42', 'partial_two' => '7'],
            ['enrollment_id' => $second->getKey(), 'diagnostic' => '3.5', 'partial' => '6', 'partial_two' => '10'],
        ]];

        $this->putJson($this->path('/grades'), $payload)->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.diagnostic_grade', '8.25')->assertJsonPath('data.0.partial_grade', '9.42')
            ->assertJsonPath('data.0.second_partial_grade', '7.00')->assertJsonPath('data.0.knowledge_group', 'Alto')
            ->assertJsonPath('data.1.knowledge_group', 'Bajo');

        // Reenviar lo mismo, o corregir, no acumula notas: queda una por etapa.
        $this->putJson($this->path('/grades'), $payload)->assertOk();
        $this->assertDatabaseCount('nota', 6);

        // Un valor inválido rechaza todo el lote: no se guarda ninguna nota.
        $invalid = ['grades' => [
            ['enrollment_id' => $first->getKey(), 'partial' => '5'],
            ['enrollment_id' => $second->getKey(), 'partial' => '10.5'],
        ]];
        $this->putJson($this->path('/grades'), $invalid)->assertUnprocessable()->assertJsonValidationErrors('grades.1.partial');
        $this->putJson($this->path('/grades'), ['grades' => [['enrollment_id' => $first->getKey(), 'partial' => '5.123']]])->assertUnprocessable();
        $this->assertDatabaseCount('nota', 6);
    }

    public function test_enrolled_students_are_listed_in_alphabetical_order(): void
    {
        $zeta = $this->enrollment();
        $zeta->estudiante->update(['nombre' => 'Zambrano Luis']);
        $alpha = $this->enrollment();
        $alpha->estudiante->update(['nombre' => 'Andrade Sofía']);
        $middle = $this->enrollment();
        $middle->estudiante->update(['nombre' => 'Loor Mateo']);

        $this->getJson($this->path('/students'))->assertOk()
            ->assertJsonPath('data.0.name', 'Andrade Sofía')->assertJsonPath('data.1.name', 'Loor Mateo')->assertJsonPath('data.2.name', 'Zambrano Luis');
    }
}

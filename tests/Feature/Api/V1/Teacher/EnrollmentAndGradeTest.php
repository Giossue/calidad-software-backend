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

    public function test_diagnostic_boundaries_classify_and_preserve_grade_history(): void
    {
        $enrollment = $this->enrollment();
        foreach ([[0, 'Bajo'], [3.99, 'Bajo'], [4, 'Medio'], [6.99, 'Medio'], [7, 'Alto'], [10, 'Alto']] as [$value, $group]) {
            $this->putJson($this->path('/students/'.$enrollment->getKey().'/grades/diagnostic'), ['value' => $value])->assertOk()->assertJsonPath('data.knowledge_group', $group);
        }
        $this->assertDatabaseCount('nota', 6);
        $this->assertDatabaseCount('metrica_conocimiento', 1);
        $this->putJson($this->path('/students/'.$enrollment->getKey().'/grades/diagnostic'), ['value' => '10.00'])->assertOk();
        $this->assertDatabaseCount('nota', 6);
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
}

<?php

namespace Tests\Feature\Api\V1\Teacher;

use App\Actions\Tutoring\AdvanceApprovedStudents;
use App\Models\AsignaturaTutoria;
use App\Models\InscripcionTutoria;
use App\Models\PeriodoAcademico;
use App\Models\Role;
use App\Models\Usuario;
use Laravel\Sanctum\Sanctum;

/**
 * Número de matrícula (1.ª, 2.ª o 3.ª) de la inscripción en la tutoría: desde la
 * 2.ª matrícula la asistencia es obligatoria.
 */
class EnrollmentNumberTest extends TeacherTestCase
{
    private const MESSAGE = 'La matrícula debe ser 1.ª, 2.ª o 3.ª.';

    private function sectionStudent(): Usuario
    {
        $student = Usuario::factory()->withRole('estudiante')->create();
        $student->paralelos()->attach($this->tutoring->fk_paralelo, ['fecha_asignacion' => today(), 'estado' => true]);

        return $student;
    }

    public function test_teacher_enrolls_in_first_matricula_by_default_and_in_second_when_sent(): void
    {
        $this->postJson($this->path('/students'), ['student_id' => $this->sectionStudent()->getKey()])->assertCreated()
            ->assertJsonPath('data.enrollment_number', 1)->assertJsonPath('data.attendance_required', false);

        $second = $this->postJson($this->path('/students'), ['student_id' => $this->sectionStudent()->getKey(), 'enrollment_number' => 2])->assertCreated()
            ->assertJsonPath('data.enrollment_number', 2)->assertJsonPath('data.attendance_required', true);
        $this->assertSame(2, InscripcionTutoria::query()->findOrFail($second->json('data.id'))->numero_matricula);

        $draft = Usuario::factory()->make();
        $this->postJson($this->path('/students'), [
            'identification' => $draft->cedula, 'first_names' => 'Ana', 'last_names' => 'Torres', 'email' => 'ana.torres@ueb.edu.ec', 'enrollment_number' => 3,
        ])->assertCreated()->assertJsonPath('data.enrollment_number', 3)->assertJsonPath('data.attendance_required', true);
    }

    public function test_teacher_rejects_a_matricula_outside_one_to_three(): void
    {
        foreach ([4, 0, 'segunda', null] as $value) {
            $this->postJson($this->path('/students'), ['student_id' => $this->sectionStudent()->getKey(), 'enrollment_number' => $value])
                ->assertUnprocessable()->assertJsonValidationErrors(['enrollment_number' => self::MESSAGE]);
        }
        $this->assertDatabaseCount('inscripcion_tutoria', 0);

        $enrollment = $this->enrollment();
        $this->patchJson($this->path("/students/{$enrollment->getKey()}"), ['enrollment_number' => 4])
            ->assertUnprocessable()->assertJsonValidationErrors(['enrollment_number' => self::MESSAGE]);
        $this->assertSame(1, $enrollment->refresh()->numero_matricula);
    }

    public function test_teacher_changes_the_matricula_when_editing_and_keeps_it_when_absent(): void
    {
        $enrollment = $this->enrollment();
        $student = $enrollment->estudiante;

        $this->patchJson($this->path("/students/{$enrollment->getKey()}"), ['phone' => '0991234567', 'enrollment_number' => 2])->assertOk()
            ->assertJsonPath('data.enrollment_number', 2)->assertJsonPath('data.attendance_required', true)->assertJsonPath('data.phone', '0991234567');
        $this->assertSame(2, $enrollment->refresh()->numero_matricula);

        $this->patchJson($this->path("/students/{$enrollment->getKey()}"), ['first_names' => 'Ana', 'last_names' => 'Mora', 'phone' => '0991234567'])->assertOk()
            ->assertJsonPath('data.enrollment_number', 2)->assertJsonPath('data.name', 'MORA ANA');
        $this->assertSame([2, 'MORA ANA'], [$enrollment->refresh()->numero_matricula, $student->refresh()->nombre]);
    }

    public function test_the_matricula_of_a_student_with_other_roles_changes_but_their_profile_does_not(): void
    {
        $enrollment = $this->enrollment();
        $student = $enrollment->estudiante;
        $student->update(['telefono' => '0991234567']);
        $student->roles()->attach(Role::query()->where('slug', 'docente')->value('id'));
        $teacherPath = $this->path("/students/{$enrollment->getKey()}");
        $coordinatorPath = self::API.'/tutorings/'.$this->tutoring->getKey()."/students/{$enrollment->getKey()}";

        $this->patchJson($teacherPath, ['phone' => '0991234567', 'enrollment_number' => 2])->assertOk()->assertJsonPath('data.enrollment_number', 2);
        $this->patchJson($teacherPath, ['phone' => '0990000000', 'enrollment_number' => 3])->assertForbidden();
        $this->patchJson($teacherPath, ['phone' => '0991234567', 'first_names' => 'Otro', 'last_names' => 'Nombre Distinto'])->assertForbidden();
        $this->assertSame(2, $enrollment->refresh()->numero_matricula);
        $this->assertSame('0991234567', $student->refresh()->telefono);

        Sanctum::actingAs($this->coordinator, ['access-api']);
        $this->patchJson($coordinatorPath, ['phone' => '0991234567', 'enrollment_number' => 3])->assertOk()->assertJsonPath('data.enrollment_number', 3);
        $this->patchJson($coordinatorPath, ['phone' => null, 'enrollment_number' => 1])->assertForbidden();
        $this->assertSame(3, $enrollment->refresh()->numero_matricula);
        $this->assertSame('0991234567', $student->refresh()->telefono);
    }

    public function test_enrollment_lists_return_the_matricula_and_whether_attendance_is_required(): void
    {
        $first = $this->enrollment();
        $second = $this->enrollment();
        $second->update(['numero_matricula' => 2]);

        $rows = collect($this->getJson($this->path('/students'))->assertOk()->json('data'))->keyBy('id');
        $this->assertSame([1, false], [$rows[$first->getKey()]['enrollment_number'], $rows[$first->getKey()]['attendance_required']]);
        $this->assertSame([2, true], [$rows[$second->getKey()]['enrollment_number'], $rows[$second->getKey()]['attendance_required']]);

        Sanctum::actingAs($this->coordinator, ['access-api']);
        $rows = collect($this->getJson(self::API."/tutorings/{$this->tutoring->getKey()}/students")->assertOk()->json('data'))->keyBy('id');
        $this->assertSame([1, false], [$rows[$first->getKey()]['enrollment_number'], $rows[$first->getKey()]['attendance_required']]);
        $this->assertSame([2, true], [$rows[$second->getKey()]['enrollment_number'], $rows[$second->getKey()]['attendance_required']]);

        $second->estudiante->update(['fk_carrera' => $this->career->getKey()]);
        $student = collect($this->getJson(self::API.'/students?per_page=50')->assertOk()->json('data'))->firstWhere('id', $second->fk_id_usuario);
        $this->assertSame(2, $student['tutorings'][0]['enrollment_number']);
        $this->assertTrue($student['tutorings'][0]['attendance_required']);
    }

    public function test_session_attendance_rows_say_whether_attendance_is_required_keeping_the_name_order(): void
    {
        $required = $this->enrollment(null, Usuario::factory()->student()->create(['nombre' => 'ZAPATA RUIZ ANA']));
        $required->update(['numero_matricula' => 2]);
        $optional = $this->enrollment(null, Usuario::factory()->student()->create(['nombre' => 'ALVAREZ MORA LUIS']));
        $this->classNow();

        $this->putJson($this->path('/sessions'), ['date' => today()->toDateString(), 'topics_covered' => false, 'attendance' => [
            ['enrollment_id' => $required->getKey(), 'present' => true],
            ['enrollment_id' => $optional->getKey(), 'present' => false],
        ]])->assertOk()
            ->assertJsonPath('data.attendance.*.student_name', ['ALVAREZ MORA LUIS', 'ZAPATA RUIZ ANA'])
            ->assertJsonPath('data.attendance.*.attendance_required', [false, true]);

        $this->getJson($this->path('/sessions'))->assertOk()->assertJsonPath('data.0.attendance.*.attendance_required', [false, true]);
    }

    public function test_coordinator_enrolls_with_a_matricula_and_changes_it_when_editing(): void
    {
        Sanctum::actingAs($this->coordinator, ['access-api']);
        $url = self::API."/tutorings/{$this->tutoring->getKey()}/students";

        $this->postJson($url, ['student_id' => $this->sectionStudent()->getKey()])->assertCreated()->assertJsonPath('data.enrollment_number', 1);
        $this->postJson($url, ['student_id' => $this->sectionStudent()->getKey(), 'enrollment_number' => 5])
            ->assertUnprocessable()->assertJsonValidationErrors(['enrollment_number' => self::MESSAGE]);
        $created = $this->postJson($url, ['student_id' => $this->sectionStudent()->getKey(), 'enrollment_number' => 2])->assertCreated()
            ->assertJsonPath('data.enrollment_number', 2)->assertJsonPath('data.attendance_required', true);

        $enrollment = InscripcionTutoria::query()->findOrFail($created->json('data.id'));
        $this->patchJson("{$url}/{$enrollment->getKey()}", ['phone' => '0991234567', 'enrollment_number' => 3])->assertOk()->assertJsonPath('data.enrollment_number', 3);
        $this->patchJson("{$url}/{$enrollment->getKey()}", ['phone' => '0991234567', 'enrollment_number' => 'tercera'])
            ->assertUnprocessable()->assertJsonValidationErrors(['enrollment_number' => self::MESSAGE]);
        $this->patchJson("{$url}/{$enrollment->getKey()}", ['phone' => '0997654321'])->assertOk()->assertJsonPath('data.enrollment_number', 3)->assertJsonPath('data.phone', '0997654321');
        $this->assertSame(3, $enrollment->refresh()->numero_matricula);
    }

    public function test_coordinator_enrolls_a_registered_student_with_a_matricula(): void
    {
        Sanctum::actingAs($this->coordinator, ['access-api']);
        $student = Usuario::factory()->student()->create(['fk_carrera' => $this->career->getKey()]);
        $url = self::API."/students/{$student->getKey()}/enroll";

        $this->postJson($url, ['tutoring_id' => $this->tutoring->getKey(), 'enrollment_number' => 4])
            ->assertUnprocessable()->assertJsonValidationErrors(['enrollment_number' => self::MESSAGE]);
        $this->assertDatabaseCount('inscripcion_tutoria', 0);

        $this->postJson($url, ['tutoring_id' => $this->tutoring->getKey(), 'enrollment_number' => 2])->assertCreated()
            ->assertJsonPath('data.enrollment_number', 2)->assertJsonPath('data.attendance_required', true);

        $other = Usuario::factory()->student()->create(['fk_carrera' => $this->career->getKey()]);
        $this->postJson(self::API."/students/{$other->getKey()}/enroll", ['tutoring_id' => $this->tutoring->getKey()])->assertCreated()
            ->assertJsonPath('data.enrollment_number', 1)->assertJsonPath('data.attendance_required', false);
    }

    public function test_coordinator_registers_a_new_student_in_an_initial_tutoring_with_a_matricula(): void
    {
        Sanctum::actingAs($this->coordinator, ['access-api']);
        $payload = [
            'identification' => '0926687856', 'first_names' => 'Carlos', 'last_names' => 'Nuevo', 'email' => 'carlos.nuevo@ueb.edu.ec',
            'cycle_id' => $this->cycle->getKey(), 'tutoring_id' => $this->tutoring->getKey(),
        ];

        $this->postJson(self::API.'/students', [...$payload, 'enrollment_number' => 9])
            ->assertUnprocessable()->assertJsonValidationErrors(['enrollment_number' => self::MESSAGE]);
        $this->assertDatabaseCount('inscripcion_tutoria', 0);

        $this->postJson(self::API.'/students', [...$payload, 'enrollment_number' => 2])->assertCreated()
            ->assertJsonPath('data.tutorings.0.enrollment_number', 2)->assertJsonPath('data.tutorings.0.attendance_required', true);

        $this->postJson(self::API.'/students', [...$payload, 'identification' => '1712345675', 'email' => 'otro.nuevo@ueb.edu.ec'])->assertCreated()
            ->assertJsonPath('data.tutorings.0.enrollment_number', 1)->assertJsonPath('data.tutorings.0.attendance_required', false);
    }

    public function test_students_advanced_to_the_next_subject_start_in_first_matricula(): void
    {
        $next = $this->createSubject($this->createCycle($this->career, null, 2), 'CS-201');
        $this->tutoring->subject->nextSubjects()->attach($next);
        $approved = $this->enrollment();
        $approved->update(['numero_matricula' => 2]);
        $this->putJson($this->path("/students/{$approved->getKey()}/final-result"), ['result' => InscripcionTutoria::APPROVED])->assertOk();

        $later = PeriodoAcademico::query()->create(['nombre' => 'PAO 2027', 'fecha_inicio' => '2026-11-01', 'fecha_fin' => '2027-03-31', 'estado' => false]);
        $nextTutoring = AsignaturaTutoria::query()->create([
            'subject_id' => $next->getKey(), 'fk_ciclo' => $this->cycle->getKey(), 'fk_periodo' => $later->getKey(),
            'fk_modalidad' => $this->modality->getKey(), 'fk_paralelo' => $this->tutoring->fk_paralelo, 'nombre' => $next->name, 'estado' => true,
        ]);
        app(AdvanceApprovedStudents::class)->intoNewTutoring($nextTutoring);

        $advanced = $nextTutoring->inscripciones()->firstOrFail();
        $this->assertSame($approved->fk_id_usuario, $advanced->fk_id_usuario);
        $this->assertSame(1, $advanced->refresh()->numero_matricula);
    }
}

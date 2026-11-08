<?php

namespace Tests\Feature\Api\V1\Teacher;

use App\Actions\Tutoring\ManageSchedule;
use App\Models\AsignaturaTutoria;
use App\Models\Usuario;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

class JointTutoringTest extends TeacherTestCase
{
    private function schedule(AsignaturaTutoria $tutoring, string $day, string $start, string $end): void
    {
        app(ManageSchedule::class)->create($tutoring, ['day' => $day, 'start_time' => $start, 'end_time' => $end, 'room' => 'Aula 1']);
    }

    /** La misma asignatura (por nombre) del mismo docente en otra carrera. */
    private function otherCareerTutoring(string $name): AsignaturaTutoria
    {
        $subject = $this->createSubject($this->otherCycle, 'OT-'.random_int(100, 999));
        $subject->update(['name' => $name]);
        $tutoring = $this->createTutoring($this->otherCycle, $subject);
        $tutoring->update(['fk_docente' => $this->teacher->getKey()]);

        return $tutoring;
    }

    public function test_the_same_subject_can_be_taught_to_two_groups_at_the_same_time(): void
    {
        $this->schedule($this->tutoring, 'lunes', '08:00', '10:00');
        $this->schedule($this->tutoring, 'miercoles', '08:00', '10:00');
        $joint = $this->otherCareerTutoring('CALIDAD DE SOFTWARE');

        // «Calidad de software» y «CALIDAD DE SOFTWARE» son la misma asignatura: no hay cruce.
        $this->schedule($joint, 'Lunes', '09:00', '11:00');
        $this->schedule($joint, 'viernes', '08:00', '10:00');

        $this->getJson($this->path('/joint-tutorings'))->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $joint->getKey())
            ->assertJsonPath('data.0.career_name', 'Administración')
            ->assertJsonPath('data.0.days', ['lunes'])
            ->assertJsonPath('data.0.can_manage', true);
        $this->getJson($this->path('/joint-tutorings', $joint))->assertOk()->assertJsonPath('data.0.id', $this->tutoring->getKey());
    }

    public function test_a_different_subject_at_the_same_time_is_still_a_conflict(): void
    {
        $this->schedule($this->tutoring, 'lunes', '08:00', '10:00');
        $other = $this->otherCareerTutoring('Estadística');

        try {
            $this->schedule($other, 'lunes', '09:00', '11:00');
            $this->fail('Se esperaba un cruce de horario.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('El docente ya está asignado a otra asignatura', (string) collect($exception->errors())->flatten()->first());
        }

        $this->getJson($this->path('/joint-tutorings'))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_only_the_assigned_teacher_sees_joint_tutorings(): void
    {
        Sanctum::actingAs(Usuario::factory()->withRole('docente')->create(), ['access-api']);
        $this->getJson($this->path('/joint-tutorings'))->assertForbidden();
    }
}

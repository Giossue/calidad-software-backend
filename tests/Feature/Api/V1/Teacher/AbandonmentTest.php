<?php

namespace Tests\Feature\Api\V1\Teacher;

use App\Actions\Tutoring\AdvanceApprovedStudents;
use App\Models\AsignaturaTutoria;
use App\Models\InscripcionTutoria;
use App\Models\Nota;
use App\Models\PeriodoAcademico;
use App\Models\Usuario;
use Laravel\Sanctum\Sanctum;

class AbandonmentTest extends TeacherTestCase
{
    private function abandonmentPath(InscripcionTutoria $enrollment): string
    {
        return $this->path("/students/{$enrollment->getKey()}/abandonment");
    }

    public function test_teacher_marks_and_clears_the_abandonment_with_its_reason(): void
    {
        $enrollment = $this->enrollment();

        $this->getJson($this->path('/students'))->assertOk()
            ->assertJsonPath('data.0.is_abandoned', false)->assertJsonPath('data.0.abandoned_at', null)->assertJsonPath('data.0.abandonment_reason', null);

        $this->putJson($this->abandonmentPath($enrollment), ['abandoned' => true, 'reason' => 'Consiguió trabajo y no puede asistir'])->assertOk()
            ->assertJsonPath('data.is_abandoned', true)
            ->assertJsonPath('data.abandonment_reason', 'Consiguió trabajo y no puede asistir')
            ->assertJsonPath('data.is_active', true);
        $enrollment->refresh();
        $this->assertNotNull($enrollment->abandoned_at);
        $this->assertTrue($enrollment->estado);

        $firstMark = $enrollment->abandoned_at;
        $this->travel(1)->hours();
        $this->putJson($this->abandonmentPath($enrollment), ['abandoned' => true, 'reason' => 'Se mudó de ciudad'])->assertOk()->assertJsonPath('data.abandonment_reason', 'Se mudó de ciudad');
        $this->assertTrue($enrollment->refresh()->abandoned_at->equalTo($firstMark));

        $this->putJson($this->abandonmentPath($enrollment), ['abandoned' => false])->assertOk()
            ->assertJsonPath('data.is_abandoned', false)->assertJsonPath('data.abandonment_reason', null);
        $this->assertNull($enrollment->refresh()->abandoned_at);
        $this->assertNull($enrollment->abandonment_reason);
    }

    public function test_marking_the_abandonment_requires_a_reason_and_a_boolean(): void
    {
        $enrollment = $this->enrollment();

        $this->putJson($this->abandonmentPath($enrollment), ['abandoned' => true])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->putJson($this->abandonmentPath($enrollment), ['abandoned' => true, 'reason' => '   '])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->putJson($this->abandonmentPath($enrollment), ['abandoned' => true, 'reason' => str_repeat('a', 1001)])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->putJson($this->abandonmentPath($enrollment), ['reason' => 'Sin indicar si abandonó'])->assertUnprocessable()->assertJsonValidationErrors('abandoned');
        $this->assertNull($enrollment->refresh()->abandoned_at);
    }

    public function test_a_disabled_enrollment_cannot_be_marked_and_only_the_assigned_teacher_can_write(): void
    {
        $disabled = $this->enrollment();
        $disabled->update(['estado' => false]);
        $this->putJson($this->abandonmentPath($disabled), ['abandoned' => true, 'reason' => 'Dejó de asistir'])->assertUnprocessable()->assertJsonValidationErrors('abandoned');

        $enrollment = $this->enrollment();
        Sanctum::actingAs(Usuario::factory()->withRole('docente')->create(), ['access-api']);
        $this->putJson($this->abandonmentPath($enrollment), ['abandoned' => true, 'reason' => 'Dejó de asistir'])->assertForbidden();
        $this->assertNull($enrollment->refresh()->abandoned_at);
    }

    public function test_an_abandoned_student_cannot_receive_grades_attendance_or_a_final_result(): void
    {
        $abandoned = $this->enrollment();
        $present = $this->enrollment();
        $this->putJson($this->abandonmentPath($abandoned), ['abandoned' => true, 'reason' => 'Dejó de asistir'])->assertOk();

        $this->putJson($this->path("/students/{$abandoned->getKey()}/grades/diagnostic"), ['value' => 8])->assertUnprocessable()->assertJsonValidationErrors('value');
        $this->assertSame(0, Nota::query()->where('fk_inscripcion', $abandoned->getKey())->count());

        $this->classNow();
        $this->putJson($this->path('/sessions'), ['date' => '2026-09-29', 'topics_covered' => false, 'attendance' => [
            ['enrollment_id' => $abandoned->getKey(), 'present' => true],
            ['enrollment_id' => $present->getKey(), 'present' => true],
        ]])->assertUnprocessable()->assertJsonValidationErrors('attendance.0.enrollment_id');
        $this->putJson($this->path('/sessions'), ['date' => '2026-09-29', 'topics_covered' => false, 'attendance' => [
            ['enrollment_id' => $present->getKey(), 'present' => true],
        ]])->assertOk();

        $this->putJson($this->path("/students/{$abandoned->getKey()}/final-result"), ['result' => InscripcionTutoria::APPROVED])->assertUnprocessable()->assertJsonValidationErrors('result');
        $this->assertNull($abandoned->refresh()->final_result);

        // Al quitar el abandono vuelve a poder calificarse.
        $this->putJson($this->abandonmentPath($abandoned), ['abandoned' => false])->assertOk();
        $this->putJson($this->path("/students/{$abandoned->getKey()}/grades/diagnostic"), ['value' => 8])->assertOk();
    }

    public function test_an_abandoned_student_is_not_enrolled_in_the_next_subject_when_its_tutoring_is_created(): void
    {
        $next = $this->createSubject($this->createCycle($this->career, null, 2), 'CS-201');
        $this->tutoring->subject->nextSubjects()->attach($next);
        $approved = $this->enrollment();
        $abandoned = $this->enrollment();
        $this->putJson($this->path("/students/{$approved->getKey()}/final-result"), ['result' => InscripcionTutoria::APPROVED])->assertOk();
        $this->putJson($this->path("/students/{$abandoned->getKey()}/final-result"), ['result' => InscripcionTutoria::APPROVED])->assertOk();
        $this->putJson($this->abandonmentPath($abandoned), ['abandoned' => true, 'reason' => 'Dejó de asistir'])->assertOk();

        $later = PeriodoAcademico::query()->create(['nombre' => 'PAO 2027', 'fecha_inicio' => '2026-11-01', 'fecha_fin' => '2027-03-31', 'estado' => false]);
        $nextTutoring = AsignaturaTutoria::query()->create([
            'subject_id' => $next->getKey(), 'fk_ciclo' => $this->cycle->getKey(), 'fk_periodo' => $later->getKey(),
            'fk_modalidad' => $this->modality->getKey(), 'fk_paralelo' => $this->tutoring->fk_paralelo, 'nombre' => $next->name, 'estado' => true,
        ]);
        app(AdvanceApprovedStudents::class)->intoNewTutoring($nextTutoring);

        $this->assertSame([$approved->fk_id_usuario], $nextTutoring->inscripciones()->pluck('fk_id_usuario')->all());
    }

    public function test_coordination_marks_and_clears_the_abandonment_of_its_tutorings(): void
    {
        $enrollment = $this->enrollment();
        $url = self::API."/tutorings/{$this->tutoring->getKey()}/students/{$enrollment->getKey()}/abandonment";
        Sanctum::actingAs($this->coordinator, ['access-api']);

        $this->putJson($url, ['abandoned' => true])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->putJson($url, ['abandoned' => true, 'reason' => 'Retiro voluntario'])->assertOk()
            ->assertJsonPath('data.is_abandoned', true)->assertJsonPath('data.abandonment_reason', 'Retiro voluntario');
        $this->assertNotNull($enrollment->refresh()->abandoned_at);
        $this->getJson(self::API."/tutorings/{$this->tutoring->getKey()}/students")->assertOk()->assertJsonPath('data.0.is_abandoned', true);

        $this->putJson($url, ['abandoned' => false])->assertOk()->assertJsonPath('data.is_abandoned', false);
        $this->assertNull($enrollment->refresh()->abandoned_at);

        $other = $this->createTutoring($this->otherCycle);
        $foreign = $this->enrollment($other);
        $this->putJson(self::API."/tutorings/{$this->tutoring->getKey()}/students/{$foreign->getKey()}/abandonment", ['abandoned' => true, 'reason' => 'Retiro voluntario'])->assertNotFound();
    }
}

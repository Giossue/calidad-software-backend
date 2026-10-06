<?php

namespace Tests\Feature\Api\V1\Tutoring;

use App\Actions\AcademicPeriods\ExpireAcademicPeriods;
use App\Models\AsignaturaTutoria;
use App\Models\Horario;
use App\Models\Paralelo;
use App\Models\Usuario;
use Laravel\Sanctum\Sanctum;

class TutoringConfigurationTest extends TutoringTestCase
{
    public function test_tutoring_is_created_with_its_teacher_and_schedules_in_one_request(): void
    {
        $teacher = Usuario::factory()->withRole('docente')->create();
        Sanctum::actingAs($this->coordinator, ['*']);

        $id = $this->postJson(self::API.'/tutorings', [
            ...$this->tutoringPayload($this->createSubject()),
            'parallel_ids' => [$this->cycle->fk_paralelo],
            'teacher_id' => $teacher->getKey(),
            'schedules' => [
                ['day' => 'lunes', 'start_time' => '10:00', 'end_time' => '12:00'],
                ['day' => 'miercoles', 'start_time' => '08:00', 'end_time' => '09:00'],
            ],
        ])->assertCreated()->assertJsonPath('data.teacher_id', $teacher->getKey())->json('data.id');

        $this->assertSame(2, Horario::query()->where('fk_asig_tutoria', $id)->where('estado', true)->count());
        $this->assertDatabaseHas('horario', ['fk_asig_tutoria' => $id, 'dia_semana' => 'lunes', 'room' => 'Por asignar']);
    }

    public function test_tutoring_creation_requires_a_schedule_and_a_single_parallel(): void
    {
        Sanctum::actingAs($this->coordinator, ['*']);
        $payload = $this->tutoringPayload($this->createSubject());
        $otherSection = Paralelo::query()->create(['nombre' => 'C', 'estado' => true]);

        $this->postJson(self::API.'/tutorings', [...$payload, 'schedules' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('schedules');
        $this->postJson(self::API.'/tutorings', [...$payload, 'parallel_ids' => [$this->cycle->fk_paralelo, $otherSection->getKey()]])
            ->assertUnprocessable()->assertJsonValidationErrors('parallel_ids');
        $this->postJson(self::API.'/tutorings', [...$payload, 'schedules' => [
            ['day' => 'lunes', 'start_time' => '10:00', 'end_time' => '11:00'],
            ['day' => 'lunes', 'start_time' => '12:00', 'end_time' => '13:00'],
        ]])->assertUnprocessable()->assertJsonValidationErrors('schedules.1.day');

        $this->assertDatabaseCount('asignatura_tutoria', 0);
    }

    public function test_tutoring_creation_is_rolled_back_when_the_teacher_is_busy(): void
    {
        $teacher = Usuario::factory()->withRole('docente')->create();
        $busy = $this->createTutoring();
        $busy->update(['fk_docente' => $teacher->getKey()]);
        Horario::query()->create([
            'fk_asig_tutoria' => $busy->getKey(), 'dia_semana' => 'lunes',
            'hora_inicio' => '10:00:00', 'hora_fin' => '12:00:00', 'room' => 'Aula 1', 'estado' => true,
        ]);
        Sanctum::actingAs($this->coordinator, ['*']);

        $this->postJson(self::API.'/tutorings', [
            ...$this->tutoringPayload($this->createSubject(code: 'CS-102')),
            'teacher_id' => $teacher->getKey(),
            'schedules' => [['day' => 'lunes', 'start_time' => '11:00', 'end_time' => '13:00']],
        ])->assertUnprocessable()->assertJsonValidationErrors('start_time');

        // Nada queda a medias: ni la tutoría ni sus horarios.
        $this->assertDatabaseCount('asignatura_tutoria', 1);
        $this->assertDatabaseCount('horario', 1);
    }

    public function test_configuration_updates_teacher_and_schedules_atomically(): void
    {
        $tutoring = $this->tutoringWithSchedule($this->createTutoring(), 'lunes', '10:00', '12:00');
        $original = Usuario::factory()->withRole('docente')->create();
        $tutoring->update(['fk_docente' => $original->getKey()]);
        $busyTeacher = Usuario::factory()->withRole('docente')->create();
        $other = $this->tutoringWithSchedule($this->createTutoring(subject: $this->createSubject(code: 'CS-102')), 'martes', '10:00', '12:00');
        $other->update(['fk_docente' => $busyTeacher->getKey()]);
        Sanctum::actingAs($this->coordinator, ['*']);
        $url = self::API.'/tutorings/'.$tutoring->getKey().'/configuration';

        // El docente nuevo choca el martes: no cambia ni el docente ni los horarios.
        $this->putJson($url, [
            'teacher_id' => $busyTeacher->getKey(),
            'schedules' => [['day' => 'martes', 'start_time' => '11:00', 'end_time' => '12:00']],
        ])->assertUnprocessable()->assertJsonValidationErrors('start_time');
        $this->assertSame($original->getKey(), $tutoring->refresh()->fk_docente);
        $this->assertSame(['lunes'], $tutoring->horarios()->where('estado', true)->pluck('dia_semana')->all());

        // Con horarios libres, docente y horarios se guardan juntos.
        $this->putJson($url, [
            'teacher_id' => $original->getKey(),
            'schedules' => [['day' => 'jueves', 'start_time' => '08:00', 'end_time' => '09:00']],
        ])->assertOk()->assertJsonPath('data.teacher_id', $original->getKey());
        $this->assertSame(['jueves'], $tutoring->horarios()->where('estado', true)->pluck('dia_semana')->all());

        $this->putJson($url, ['teacher_id' => $original->getKey(), 'schedules' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('schedules');
    }

    public function test_tutoring_cannot_be_deactivated_and_expires_with_its_academic_period(): void
    {
        $tutoring = $this->tutoringWithSchedule($this->createTutoring(), 'lunes', '10:00', '12:00');
        Sanctum::actingAs($this->coordinator, ['*']);

        $this->patchJson(self::API.'/tutorings/'.$tutoring->getKey().'/deactivate')->assertNotFound();
        $this->assertTrue($tutoring->refresh()->estado);

        $this->travelTo('2026-11-01 12:00:00');
        app(ExpireAcademicPeriods::class)->handle();

        $this->assertFalse($tutoring->refresh()->estado);
        $this->assertFalse($this->period->refresh()->estado);
    }

    public function test_teacher_cannot_remove_the_last_schedule_of_a_tutoring(): void
    {
        $teacher = Usuario::factory()->withRole('docente')->create();
        $tutoring = $this->tutoringWithSchedule($this->createTutoring(), 'lunes', '10:00', '12:00');
        $tutoring->update(['fk_docente' => $teacher->getKey()]);
        $schedule = $tutoring->horarios()->firstOrFail();
        Sanctum::actingAs($teacher, ['*']);

        $this->patchJson('/api/v1/teacher/tutorings/'.$tutoring->getKey().'/schedules/'.$schedule->getKey().'/deactivate')
            ->assertUnprocessable()->assertJsonValidationErrors('schedule');
        $this->assertTrue($schedule->refresh()->estado);
    }

    public function test_available_teachers_include_their_busy_schedules_in_the_current_period(): void
    {
        $teacher = Usuario::factory()->withRole('docente')->create();
        $tutoring = $this->tutoringWithSchedule($this->createTutoring(), 'lunes', '10:00', '12:00');
        $tutoring->update(['fk_docente' => $teacher->getKey()]);
        Sanctum::actingAs($this->coordinator, ['*']);

        $this->getJson(self::API.'/available-teachers')->assertOk()
            ->assertJsonPath('data.0.busy_schedules.0.tutoring_id', $tutoring->getKey())
            ->assertJsonPath('data.0.busy_schedules.0.day', 'lunes')
            ->assertJsonPath('data.0.busy_schedules.0.start_time', '10:00')
            ->assertJsonPath('data.0.busy_schedules.0.end_time', '12:00');
    }

    private function tutoringWithSchedule(AsignaturaTutoria $tutoring, string $day, string $start, string $end): AsignaturaTutoria
    {
        Horario::query()->create([
            'fk_asig_tutoria' => $tutoring->getKey(), 'dia_semana' => $day,
            'hora_inicio' => "{$start}:00", 'hora_fin' => "{$end}:00", 'room' => 'Aula 101', 'estado' => true,
        ]);

        return $tutoring;
    }
}

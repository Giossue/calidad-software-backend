<?php

namespace Tests\Feature\Api\V1\Tutoring;

use App\Models\AsignaturaTutoria;
use App\Models\Horario;
use App\Models\PeriodoAcademico;
use App\Models\Usuario;
use Laravel\Sanctum\Sanctum;

class TeacherScheduleConflictTest extends TutoringTestCase
{
    private Usuario $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = Usuario::factory()->withRole('docente')->create();
    }

    public function test_schedule_cannot_overlap_another_tutoring_of_the_same_teacher(): void
    {
        $first = $this->tutoringWithSchedule('CS-101', '10:00', '12:00', teacher: true);
        $second = $this->createTutoring(subject: $this->createSubject(code: 'CS-102'));
        $second->update(['fk_docente' => $this->teacher->getKey()]);
        Sanctum::actingAs($this->coordinator, ['*']);
        $url = self::API.'/tutorings/'.$second->getKey().'/schedules';

        $this->postJson($url, $this->slot('11:00', '13:00'))
            ->assertUnprocessable()->assertJsonValidationErrors('start_time');

        // Franjas contiguas no se superponen.
        $id = $this->postJson($url, $this->slot('12:00', '13:00'))->assertCreated()->json('data.id');
        $this->patchJson($url.'/'.$id, ['start_time' => '09:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('start_time');

        $this->assertSame(1, Horario::query()->where('fk_asig_tutoria', $first->getKey())->count());
    }

    public function test_schedule_overlap_is_allowed_for_different_teachers_or_days(): void
    {
        $this->tutoringWithSchedule('CS-101', '10:00', '12:00', teacher: true);
        $other = $this->createTutoring(subject: $this->createSubject(code: 'CS-102'));
        $other->update(['fk_docente' => Usuario::factory()->withRole('docente')->create()->getKey()]);
        $mine = $this->createTutoring(subject: $this->createSubject(code: 'CS-103'));
        $mine->update(['fk_docente' => $this->teacher->getKey()]);
        Sanctum::actingAs($this->coordinator, ['*']);

        $this->postJson(self::API.'/tutorings/'.$other->getKey().'/schedules', $this->slot('10:00', '12:00'))->assertCreated();
        $this->postJson(self::API.'/tutorings/'.$mine->getKey().'/schedules', [...$this->slot('10:00', '12:00'), 'day' => 'martes'])->assertCreated();
    }

    public function test_teacher_cannot_be_assigned_to_a_tutoring_at_the_same_time(): void
    {
        $this->tutoringWithSchedule('CS-101', '10:00', '12:00', teacher: true);
        $second = $this->tutoringWithSchedule('CS-102', '11:30', '13:00');
        Sanctum::actingAs($this->coordinator, ['*']);

        $this->putJson(self::API.'/tutorings/'.$second->getKey().'/teacher', ['teacher_id' => $this->teacher->getKey()])
            ->assertUnprocessable()->assertJsonValidationErrors('teacher_id');
        $this->assertNull($second->refresh()->fk_docente);
    }

    public function test_reactivating_a_tutoring_rejects_a_teacher_schedule_conflict(): void
    {
        $first = $this->tutoringWithSchedule('CS-101', '10:00', '12:00', teacher: true);
        Sanctum::actingAs($this->coordinator, ['*']);
        $url = self::API.'/tutorings/'.$first->getKey();
        $first->update(['estado' => false]);

        // Mientras estuvo deshabilitada, el docente recibió otra tutoría en la misma franja.
        $this->tutoringWithSchedule('CS-102', '10:00', '12:00', teacher: true);

        $this->patchJson($url.'/activate')->assertUnprocessable()->assertJsonValidationErrors('teacher_id');
        $this->assertFalse($first->refresh()->estado);
    }

    public function test_moving_a_tutoring_to_another_period_rejects_a_teacher_schedule_conflict(): void
    {
        $oldPeriod = PeriodoAcademico::query()->create([
            'nombre' => 'PAO 2025', 'fecha_inicio' => '2025-05-01', 'fecha_fin' => '2025-10-31', 'estado' => false,
        ]);
        $this->tutoringWithSchedule('CS-101', '10:00', '12:00', teacher: true);
        $moving = $this->tutoringWithSchedule('CS-102', '10:00', '12:00', teacher: true, periodId: $oldPeriod->getKey());
        Sanctum::actingAs($this->coordinator, ['*']);

        $this->patchJson(self::API.'/tutorings/'.$moving->getKey(), ['period_id' => $this->period->getKey()])
            ->assertUnprocessable()->assertJsonValidationErrors('period_id');
        $this->assertSame($oldPeriod->getKey(), $moving->refresh()->fk_periodo);
    }

    private function tutoringWithSchedule(string $code, string $start, string $end, bool $teacher = false, ?int $periodId = null): AsignaturaTutoria
    {
        $tutoring = $this->createTutoring(subject: $this->createSubject(code: $code));
        $tutoring->update([
            'fk_docente' => $teacher ? $this->teacher->getKey() : null,
            'fk_periodo' => $periodId ?? $this->period->getKey(),
        ]);
        Horario::query()->create([
            'fk_asig_tutoria' => $tutoring->getKey(), 'dia_semana' => 'lunes',
            'hora_inicio' => "{$start}:00", 'hora_fin' => "{$end}:00", 'room' => 'Aula 101', 'estado' => true,
        ]);

        return $tutoring;
    }

    /** @return array{day: string, start_time: string, end_time: string, room: string} */
    private function slot(string $start, string $end): array
    {
        return ['day' => 'lunes', 'start_time' => $start, 'end_time' => $end, 'room' => 'Aula 202'];
    }
}

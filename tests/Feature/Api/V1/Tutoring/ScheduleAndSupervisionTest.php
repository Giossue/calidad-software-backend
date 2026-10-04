<?php

namespace Tests\Feature\Api\V1\Tutoring;

use App\Models\AsignaturaTutoria;
use App\Models\Asistencia;
use App\Models\Horario;
use App\Models\InscripcionTutoria;
use App\Models\Reporte;
use App\Models\Usuario;
use Laravel\Sanctum\Sanctum;

class ScheduleAndSupervisionTest extends TutoringTestCase
{
    public function test_coordinator_can_create_edit_and_deactivate_schedules(): void
    {
        $tutoring = $this->createTutoring();
        Sanctum::actingAs($this->coordinator, ['*']);
        $url = self::API.'/tutorings/'.$tutoring->getKey().'/schedules';
        $response = $this->postJson($url, $this->schedulePayload())->assertCreated()
            ->assertJsonPath('data.tutoring_id', $tutoring->getKey())->assertJsonPath('data.room', 'Aula 101');
        $id = $response->json('data.id');
        $this->patchJson($url.'/'.$id, ['end_time' => '12:00', 'room' => 'Aula 202'])->assertOk()
            ->assertJsonPath('data.start_time', '10:00')->assertJsonPath('data.end_time', '12:00')->assertJsonPath('data.room', 'Aula 202');
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
        $this->patchJson($url.'/'.$id.'/deactivate')->assertOk()->assertJsonPath('data.is_active', false);
        $this->assertDatabaseHas('horario', ['id_horario' => $id, 'estado' => false]);
        $this->postJson($url, $this->schedulePayload())->assertCreated();
        $this->assertDatabaseCount('horario', 2);
    }

    public function test_schedule_rejects_invalid_times_days_and_empty_rooms_on_create_and_partial_update(): void
    {
        $tutoring = $this->createTutoring();
        Sanctum::actingAs($this->coordinator, ['*']);
        $url = self::API.'/tutorings/'.$tutoring->getKey().'/schedules';
        foreach ([
            ['end_time' => '09:00'], ['end_time' => '10:00'], ['start_time' => '25:00'],
            ['day' => 'feriado'], ['room' => '   '],
        ] as $invalid) {
            $this->postJson($url, array_replace($this->schedulePayload(), $invalid))->assertUnprocessable();
        }
        $id = $this->postJson($url, $this->schedulePayload())->assertCreated()->json('data.id');
        $this->patchJson($url.'/'.$id, ['start_time' => '12:00'])->assertUnprocessable()->assertJsonValidationErrors('end_time');
        $this->assertSame('10:00', substr(Horario::query()->findOrFail($id)->hora_inicio, 0, 5));
    }

    public function test_schedule_rejects_overlaps_and_accepts_adjacent_hours_on_both_sides(): void
    {
        $tutoring = $this->createTutoring();
        Sanctum::actingAs($this->coordinator, ['*']);
        $url = self::API.'/tutorings/'.$tutoring->getKey().'/schedules';
        $this->postJson($url, $this->schedulePayload())->assertCreated();
        $this->postJson($url, array_replace($this->schedulePayload(), ['start_time' => '10:30', 'end_time' => '11:30']))
            ->assertUnprocessable()->assertJsonValidationErrors('start_time');
        $this->postJson($url, array_replace($this->schedulePayload(), ['start_time' => '09:00', 'end_time' => '10:00']))->assertCreated();
        $this->postJson($url, array_replace($this->schedulePayload(), ['start_time' => '11:00', 'end_time' => '12:00']))->assertCreated();
        $this->postJson($url, array_replace($this->schedulePayload(), ['day' => 'martes']))->assertCreated();
    }

    public function test_schedule_update_cannot_move_into_another_active_schedule(): void
    {
        $tutoring = $this->createTutoring();
        Sanctum::actingAs($this->coordinator, ['*']);
        $url = self::API.'/tutorings/'.$tutoring->getKey().'/schedules';
        $this->postJson($url, $this->schedulePayload())->assertCreated();
        $id = $this->postJson($url, array_replace($this->schedulePayload(), ['start_time' => '12:00', 'end_time' => '13:00']))
            ->assertCreated()->json('data.id');
        $this->patchJson($url.'/'.$id, ['start_time' => '10:30'])->assertUnprocessable()->assertJsonValidationErrors('start_time');
    }

    public function test_schedule_must_belong_to_the_tutoring_in_the_url(): void
    {
        $tutoring = $this->createTutoring();
        $other = $this->createTutoring(subject: $this->createSubject(code: 'CS-102'));
        Sanctum::actingAs($this->coordinator, ['*']);
        $id = $this->postJson(self::API.'/tutorings/'.$other->getKey().'/schedules', $this->schedulePayload())->assertCreated()->json('data.id');
        $url = self::API.'/tutorings/'.$tutoring->getKey().'/schedules/'.$id;
        $this->patchJson($url, ['room' => 'Cambio indebido'])->assertNotFound();
        $this->patchJson($url.'/deactivate')->assertNotFound();
        $this->assertDatabaseHas('horario', ['id_horario' => $id, 'room' => 'Aula 101', 'estado' => true]);
    }

    public function test_schedules_from_other_careers_cannot_be_read_or_modified(): void
    {
        $tutoring = $this->createTutoring($this->otherCycle);
        $schedule = Horario::query()->create([
            'fk_asig_tutoria' => $tutoring->getKey(), 'dia_semana' => 'lunes',
            'hora_inicio' => '10:00:00', 'hora_fin' => '11:00:00', 'room' => 'Aula 101', 'estado' => true,
        ]);
        Sanctum::actingAs($this->coordinator, ['*']);
        $url = self::API.'/tutorings/'.$tutoring->getKey().'/schedules';
        $this->getJson($url)->assertForbidden();
        $this->postJson($url, $this->schedulePayload())->assertForbidden();
        $this->patchJson($url.'/'.$schedule->getKey(), ['room' => 'Otra'])->assertForbidden();
        $this->patchJson($url.'/'.$schedule->getKey().'/deactivate')->assertForbidden();
    }

    public function test_inactive_tutoring_rejects_new_schedules_without_losing_existing_schedules(): void
    {
        $tutoring = $this->createTutoring();
        Sanctum::actingAs($this->coordinator, ['*']);
        $url = self::API.'/tutorings/'.$tutoring->getKey();
        $this->postJson($url.'/schedules', $this->schedulePayload())->assertCreated();
        $this->patchJson($url.'/deactivate')->assertOk();
        $this->postJson($url.'/schedules', $this->schedulePayload())->assertUnprocessable();
        $this->getJson($url.'/schedules')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_supervision_returns_only_records_of_the_authorized_tutoring_and_preserves_history(): void
    {
        $own = $this->createTutoring();
        $other = $this->createTutoring(subject: $this->createSubject(code: 'CS-102'));
        $foreign = $this->createTutoring($this->otherCycle);
        [$attendance, $report] = $this->createSupervisionRecords($own);
        $this->createSupervisionRecords($other);
        $this->createSupervisionRecords($foreign);
        Sanctum::actingAs($this->coordinator, ['*']);
        $url = self::API.'/tutorings/'.$own->getKey();
        $this->getJson($url.'/attendance')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $attendance->getKey())->assertJsonPath('data.0.present', true)
            ->assertJsonPath('data.0.student_name', $attendance->estudiante->nombre);
        $this->getJson($url.'/reports')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $report->getKey())->assertJsonPath('data.0.type', 'Seguimiento')
            ->assertJsonPath('data.0.content', null);
        $this->getJson(self::API.'/tutorings/'.$foreign->getKey().'/attendance')->assertForbidden();
        $this->getJson(self::API.'/tutorings/'.$foreign->getKey().'/reports')->assertForbidden();
        $this->postJson($url.'/attendance', [])->assertMethodNotAllowed();
        $this->postJson($url.'/reports', [])->assertMethodNotAllowed();

        $this->patchJson($url.'/deactivate')->assertOk();
        $this->getJson($url.'/attendance')->assertOk()->assertJsonPath('data.0.id', $attendance->getKey());
        $this->getJson($url.'/reports')->assertOk()->assertJsonPath('data.0.id', $report->getKey());
        $this->assertDatabaseCount('inscripcion_tutoria', 3);
        $this->assertDatabaseCount('asistencia', 3);
        $this->assertDatabaseCount('reporte', 3);
    }

    public function test_reports_include_content_and_summary_for_only_the_selected_tutoring(): void
    {
        $own = $this->createTutoring();
        $foreign = $this->createTutoring($this->otherCycle);
        [, $report] = $this->createSupervisionRecords($own);
        [$absent] = $this->createSupervisionRecords($own);
        $absent->update(['estado_asistencia' => false]);
        $report->update(['content' => 'Se reforzó la validación de requisitos.']);
        $this->createSupervisionRecords($foreign);
        Sanctum::actingAs($this->coordinator, ['*']);
        $response = $this->getJson(self::API.'/tutorings/'.$own->getKey().'/reports')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['content' => 'Se reforzó la validación de requisitos.'])
            ->assertJsonPath('meta.enrollment_count', 2)
            ->assertJsonPath('meta.present_count', 1)->assertJsonPath('meta.absent_count', 1);
        $this->assertSame(2, $response->json('meta.total'));
        $this->getJson(self::API.'/tutorings/'.$own->getKey().'/reports?per_page=1')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('meta.enrollment_count', 2)
            ->assertJsonPath('meta.present_count', 1)->assertJsonPath('meta.absent_count', 1);
    }

    public function test_schedule_rejects_overlap_for_same_teacher_across_different_tutorings(): void
    {
        $teacher = Usuario::factory()->withRole('docente')->create();
        $tutoring1 = $this->createTutoring();
        $tutoring2 = $this->createTutoring(subject: $this->createSubject(code: 'CS-202'));

        Sanctum::actingAs($this->coordinator, ['*']);

        // Asignar docente a tutoría 1 y crear horario lunes 10:00 - 11:00
        $this->putJson(self::API.'/tutorings/'.$tutoring1->getKey().'/teacher', ['teacher_id' => $teacher->getKey()])
            ->assertOk();
        $this->postJson(self::API.'/tutorings/'.$tutoring1->getKey().'/schedules', [
            'day' => 'lunes', 'start_time' => '10:00', 'end_time' => '11:00', 'room' => 'Aula 101',
        ])->assertCreated();

        // Asignar mismo docente a tutoría 2
        $this->putJson(self::API.'/tutorings/'.$tutoring2->getKey().'/teacher', ['teacher_id' => $teacher->getKey()])
            ->assertOk();

        // Intentar crear horario que se solapa en tutoría 2 (lunes 10:30 - 11:30)
        $this->postJson(self::API.'/tutorings/'.$tutoring2->getKey().'/schedules', [
            'day' => 'lunes', 'start_time' => '10:30', 'end_time' => '11:30', 'room' => 'Aula 202',
        ])->assertUnprocessable()->assertJsonValidationErrors('start_time');

        // Un horario contiguo (lunes 11:00 - 12:00) o en otro día sí debe ser permitido
        $this->postJson(self::API.'/tutorings/'.$tutoring2->getKey().'/schedules', [
            'day' => 'lunes', 'start_time' => '11:00', 'end_time' => '12:00', 'room' => 'Aula 202',
        ])->assertCreated();
    }

    public function test_assign_teacher_rejects_when_tutoring_has_conflicting_schedules(): void
    {
        $teacher = Usuario::factory()->withRole('docente')->create();
        $tutoring1 = $this->createTutoring();
        $tutoring2 = $this->createTutoring(subject: $this->createSubject(code: 'CS-203'));

        Sanctum::actingAs($this->coordinator, ['*']);

        // Tutoría 1 tiene al docente en lunes 10:00 - 11:00
        $this->putJson(self::API.'/tutorings/'.$tutoring1->getKey().'/teacher', ['teacher_id' => $teacher->getKey()])
            ->assertOk();
        $this->postJson(self::API.'/tutorings/'.$tutoring1->getKey().'/schedules', [
            'day' => 'lunes', 'start_time' => '10:00', 'end_time' => '11:00', 'room' => 'Aula 101',
        ])->assertCreated();

        // Tutoría 2 ya tiene horario lunes 10:00 - 11:00 sin docente
        $this->postJson(self::API.'/tutorings/'.$tutoring2->getKey().'/schedules', [
            'day' => 'lunes', 'start_time' => '10:00', 'end_time' => '11:00', 'room' => 'Lab 1',
        ])->assertCreated();

        // Intentar asignar al mismo docente a tutoría 2 debe ser rechazado por conflicto
        $this->putJson(self::API.'/tutorings/'.$tutoring2->getKey().'/teacher', ['teacher_id' => $teacher->getKey()])
            ->assertUnprocessable()->assertJsonValidationErrors('teacher_id');
    }

    private function schedulePayload(): array
    {
        return ['day' => 'lunes', 'start_time' => '10:00', 'end_time' => '11:00', 'room' => 'Aula 101'];
    }

    private function createSupervisionRecords(AsignaturaTutoria $tutoring): array
    {
        $student = Usuario::factory()->withRole('estudiante')->create();
        $enrollment = InscripcionTutoria::query()->create([
            'fk_asig_tutoria' => $tutoring->getKey(), 'fk_id_usuario' => $student->getKey(),
            'fecha_inscripcion' => '2026-09-01', 'estado' => true,
        ]);
        $attendance = Asistencia::query()->create([
            'fk_inscripcion' => $enrollment->getKey(), 'fk_id_usuario' => $student->getKey(),
            'fecha' => '2026-09-10', 'estado_asistencia' => true,
        ]);
        $report = Reporte::query()->create([
            'fk_asig_tutoria' => $tutoring->getKey(), 'fk_id_usuario' => $this->coordinator->getKey(),
            'tipo_reporte' => 'Seguimiento', 'fecha_generacion' => now(),
        ]);

        return [$attendance, $report];
    }
}

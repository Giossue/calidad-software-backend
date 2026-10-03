<?php

namespace Tests\Feature\Api\V1\Teacher;

use App\Models\Horario;
use App\Models\Usuario;
use Laravel\Sanctum\Sanctum;

class TeacherScheduleTest extends TeacherTestCase
{
    public function test_teacher_can_list_schedules_for_own_tutoring(): void
    {
        Horario::query()->create([
            'fk_asig_tutoria' => $this->tutoring->getKey(),
            'dia_semana' => 'lunes',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '10:00:00',
            'room' => 'Aula 101',
            'estado' => true,
        ]);

        $response = $this->getJson($this->path('/schedules'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.day', 'lunes');
        $response->assertJsonPath('data.0.start_time', '08:00');
        $response->assertJsonPath('data.0.end_time', '10:00');
    }

    public function test_teacher_can_sync_schedules(): void
    {
        // Existing schedule on lunes
        $existing = Horario::query()->create([
            'fk_asig_tutoria' => $this->tutoring->getKey(),
            'dia_semana' => 'lunes',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '10:00:00',
            'room' => 'Lab 1',
            'estado' => true,
        ]);

        // Sync with lunes updated hours and new miercoles
        $response = $this->putJson($this->path('/schedules'), [
            'schedules' => [
                ['day' => 'lunes', 'start_time' => '09:00', 'end_time' => '11:00'],
                ['day' => 'miercoles', 'start_time' => '14:00', 'end_time' => '16:00'],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        // Existing schedule updated, room preserved
        $this->assertDatabaseHas('horario', [
            'id_horario' => $existing->getKey(),
            'dia_semana' => 'lunes',
            'hora_inicio' => '09:00:00',
            'hora_fin' => '11:00:00',
            'room' => 'Lab 1',
            'estado' => true,
        ]);

        // New schedule created with default room
        $this->assertDatabaseHas('horario', [
            'fk_asig_tutoria' => $this->tutoring->getKey(),
            'dia_semana' => 'miercoles',
            'hora_inicio' => '14:00:00',
            'hora_fin' => '16:00:00',
            'room' => 'Por asignar',
            'estado' => true,
        ]);
    }

    public function test_sync_deactivates_removed_days(): void
    {
        $lunes = Horario::query()->create([
            'fk_asig_tutoria' => $this->tutoring->getKey(),
            'dia_semana' => 'lunes',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '10:00:00',
            'room' => 'Lab 1',
            'estado' => true,
        ]);

        $martes = Horario::query()->create([
            'fk_asig_tutoria' => $this->tutoring->getKey(),
            'dia_semana' => 'martes',
            'hora_inicio' => '10:00:00',
            'hora_fin' => '12:00:00',
            'room' => 'Lab 2',
            'estado' => true,
        ]);

        // Only keep martes
        $response = $this->putJson($this->path('/schedules'), [
            'schedules' => [
                ['day' => 'martes', 'start_time' => '10:00', 'end_time' => '12:00'],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');

        $this->assertDatabaseHas('horario', [
            'id_horario' => $lunes->getKey(),
            'estado' => false,
        ]);
        $this->assertDatabaseHas('horario', [
            'id_horario' => $martes->getKey(),
            'estado' => true,
        ]);
    }

    public function test_teacher_cannot_manage_other_teachers_tutoring_schedules(): void
    {
        $otherTeacher = Usuario::factory()->withRole('docente')->create();
        Sanctum::actingAs($otherTeacher, ['access-api']);

        $response = $this->putJson($this->path('/schedules'), [
            'schedules' => [
                ['day' => 'viernes', 'start_time' => '08:00', 'end_time' => '10:00'],
            ],
        ]);

        $response->assertForbidden();
    }

    public function test_sync_validates_end_time_after_start_time(): void
    {
        $response = $this->putJson($this->path('/schedules'), [
            'schedules' => [
                ['day' => 'lunes', 'start_time' => '10:00', 'end_time' => '08:00'],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('schedules.0.end_time');
    }
}

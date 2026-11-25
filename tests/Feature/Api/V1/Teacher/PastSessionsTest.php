<?php

namespace Tests\Feature\Api\V1\Teacher;

use App\Models\Asistencia;
use App\Models\Horario;
use App\Models\InscripcionTutoria;
use App\Models\Tema;

/**
 * El docente registra o modifica la sesión de cualquier día con clase del período
 * vigente, desde su inicio hasta hoy (hora local de la universidad).
 * Hoy (setUp): martes 2026-09-29, 07:00 en Ecuador; el período va del 2026-05-01 al 2026-10-31.
 */
class PastSessionsTest extends TeacherTestCase
{
    private function schedule(string $day, bool $active = true): Horario
    {
        return Horario::query()->create([
            'fk_asig_tutoria' => $this->tutoring->getKey(), 'dia_semana' => $day,
            'hora_inicio' => '08:00:00', 'hora_fin' => '10:00:00', 'room' => 'Aula 1', 'estado' => $active,
        ]);
    }

    /** @return array{date: string, topics_covered: bool, attendance: array<int, array{enrollment_id: int, present: bool}>} */
    private function payload(InscripcionTutoria $enrollment, string $date, bool $present = true): array
    {
        return ['date' => $date, 'topics_covered' => false, 'attendance' => [['enrollment_id' => $enrollment->getKey(), 'present' => $present]]];
    }

    public function test_a_past_class_day_session_can_be_created_and_edited(): void
    {
        $this->schedule('lunes');
        $enrollment = $this->enrollment();
        $topic = Tema::query()->create(['fk_asig_tutoria' => $this->tutoring->getKey(), 'nombre' => 'Pruebas', 'estado' => true]);

        // Lunes 14 de septiembre: dos semanas atrás.
        $this->putJson($this->path('/sessions'), $this->payload($enrollment, '2026-09-14'))->assertOk()
            ->assertJsonPath('data.date', '2026-09-14')->assertJsonPath('data.attendance.0.present', true);

        // Se modifica durante todo el período: asistencia y temas.
        $this->putJson($this->path('/sessions'), [...$this->payload($enrollment, '2026-09-14', false), 'topics_covered' => true, 'topic_ids' => [$topic->getKey()]])
            ->assertOk()->assertJsonPath('data.attendance.0.present', false)->assertJsonPath('data.topics.0.id', $topic->getKey());

        $this->assertDatabaseCount('tutoring_sessions', 1);
        $this->assertDatabaseCount('asistencia', 1);
        $this->assertFalse((bool) Asistencia::query()->where('fk_inscripcion', $enrollment->getKey())->whereDate('fecha', '2026-09-14')->value('estado_asistencia'));
        $this->assertTrue($topic->refresh()->visto);
    }

    public function test_today_can_be_registered_outside_the_class_hours(): void
    {
        // Martes de 08:00 a 10:00; son las 07:00 locales.
        $this->schedule('martes');
        $enrollment = $this->enrollment();

        $this->putJson($this->path('/sessions'), $this->payload($enrollment, '2026-09-29'))->assertOk();
        $this->assertDatabaseCount('tutoring_sessions', 1);
    }

    public function test_future_dates_are_rejected(): void
    {
        $this->schedule('lunes');
        $enrollment = $this->enrollment();

        $this->putJson($this->path('/sessions'), $this->payload($enrollment, '2026-10-05'))->assertUnprocessable()
            ->assertJsonValidationErrors(['date' => 'No se pueden registrar sesiones de fechas futuras.']);
        $this->assertDatabaseCount('tutoring_sessions', 0);
    }

    public function test_the_future_is_measured_in_the_local_time_of_the_university(): void
    {
        // 20:06 del martes 29 en Ecuador ya es miércoles 30 en UTC: el miércoles aún no llega.
        $this->travelTo('2026-09-30 01:06:00');
        $this->schedule('miercoles');
        $enrollment = $this->enrollment();

        $this->putJson($this->path('/sessions'), $this->payload($enrollment, '2026-09-30'))->assertUnprocessable()
            ->assertJsonValidationErrors(['date' => 'fechas futuras']);
        $this->assertDatabaseCount('tutoring_sessions', 0);
    }

    public function test_dates_before_the_start_of_the_period_are_rejected(): void
    {
        $this->schedule('lunes');
        $enrollment = $this->enrollment();

        // Lunes 27 de abril: el período empieza el 1 de mayo.
        $this->putJson($this->path('/sessions'), $this->payload($enrollment, '2026-04-27'))->assertUnprocessable()
            ->assertJsonValidationErrors(['date' => 'La fecha debe estar dentro del período académico de la tutoría']);
        $this->assertDatabaseCount('tutoring_sessions', 0);
    }

    public function test_days_without_class_are_rejected(): void
    {
        $this->schedule('lunes');
        // Un horario deshabilitado no cuenta como día de clase.
        $this->schedule('jueves', active: false);
        $enrollment = $this->enrollment();

        foreach (['2026-09-24', '2026-09-23'] as $date) {
            $this->putJson($this->path('/sessions'), $this->payload($enrollment, $date))->assertUnprocessable()
                ->assertJsonValidationErrors(['date' => 'La tutoría no tiene clase ese día.']);
        }
        $this->assertDatabaseCount('tutoring_sessions', 0);
    }

    public function test_tutorings_of_a_closed_period_are_read_only(): void
    {
        $this->schedule('lunes');
        $enrollment = $this->enrollment();
        $this->period->update(['estado' => false]);

        $this->putJson($this->path('/sessions'), $this->payload($enrollment, '2026-09-28'))->assertForbidden();
        $this->assertDatabaseCount('tutoring_sessions', 0);
        $this->assertDatabaseCount('asistencia', 0);
    }
}

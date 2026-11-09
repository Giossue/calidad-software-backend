<?php

namespace Tests\Feature\Api\V1\Teacher;

use App\Models\Horario;
use App\Models\Usuario;
use App\Notifications\ActionNotification;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

class AbsenceNoticeTest extends TeacherTestCase
{
    private const STUDENT_API = '/api/v1/student/tutoring/';

    public function test_student_notice_reaches_the_teacher_before_taking_attendance(): void
    {
        $noticed = $this->enrollment();
        $other = $this->enrollment();
        $student = $noticed->estudiante;
        $url = self::STUDENT_API.$this->tutoring->getKey().'/absence-notices';

        Sanctum::actingAs($student, ['access-api']);
        $this->postJson($url, ['date' => '2026-09-29', 'reason' => 'Cita médica programada'])
            ->assertCreated()->assertJsonPath('data.status', 'pendiente');
        $this->postJson($url, ['date' => '2026-09-29', 'reason' => 'Otro motivo'])->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->postJson($url, ['date' => '2026-09-28', 'reason' => 'Ya pasó'])->assertUnprocessable()->assertJsonValidationErrors('date');
        Notification::assertSentTo($this->teacher, ActionNotification::class,
            fn (ActionNotification $notification): bool => $notification->kind === 'absence_notice' && str_contains($notification->body, 'Cita médica programada'));

        Sanctum::actingAs($this->teacher, ['access-api']);
        $this->getJson($this->path('/absence-notices?date=2026-09-29'))->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.enrollment_id', $noticed->getKey())
            ->assertJsonPath('data.0.reason', 'Cita médica programada');

        // El aviso es informativo: el docente decide si el estudiante queda presente o ausente.
        $this->classNow();
        $this->putJson($this->path('/sessions'), ['date' => '2026-09-29', 'topics_covered' => false, 'attendance' => [
            ['enrollment_id' => $noticed->getKey(), 'present' => false],
            ['enrollment_id' => $other->getKey(), 'present' => true],
        ]])->assertOk();

        Sanctum::actingAs($student, ['access-api']);
        $this->getJson($url)->assertOk()->assertJsonPath('data.0.status', 'ausente');
    }

    public function test_student_gets_upcoming_class_dates_without_a_notice(): void
    {
        // Hoy es martes 2026-09-29 (TeacherTestCase); el período termina el martes 2026-10-20.
        $this->tutoring->periodo->update(['fecha_fin' => '2026-10-20']);
        foreach ([['martes', '10:00:00', '12:00:00'], ['jueves', '14:00:00', '16:00:00'], ['viernes', '08:00:00', '09:00:00']] as [$day, $start, $end]) {
            Horario::query()->create(['fk_asig_tutoria' => $this->tutoring->getKey(), 'dia_semana' => $day, 'hora_inicio' => $start, 'hora_fin' => $end, 'estado' => $day !== 'viernes']);
        }
        $enrollment = $this->enrollment();
        $url = self::STUDENT_API.$this->tutoring->getKey().'/absence-notices';

        Sanctum::actingAs($enrollment->estudiante, ['access-api']);
        $this->postJson($url, ['date' => '2026-10-01', 'reason' => 'Cita médica programada'])->assertCreated();

        // Solo días con horario activo, desde hoy hasta el fin del período y sin aviso previo.
        $this->getJson($url.'/available-dates')->assertOk()
            ->assertExactJson(['data' => [
                ['date' => '2026-09-29', 'day_of_week' => 'martes', 'start_time' => '10:00', 'end_time' => '12:00'],
                ['date' => '2026-10-06', 'day_of_week' => 'martes', 'start_time' => '10:00', 'end_time' => '12:00'],
                ['date' => '2026-10-08', 'day_of_week' => 'jueves', 'start_time' => '14:00', 'end_time' => '16:00'],
                ['date' => '2026-10-13', 'day_of_week' => 'martes', 'start_time' => '10:00', 'end_time' => '12:00'],
                ['date' => '2026-10-15', 'day_of_week' => 'jueves', 'start_time' => '14:00', 'end_time' => '16:00'],
                ['date' => '2026-10-20', 'day_of_week' => 'martes', 'start_time' => '10:00', 'end_time' => '12:00'],
            ]]);

        // Cada fecha ofrecida pasa la validación del envío.
        $this->postJson($url, ['date' => '2026-10-20', 'reason' => 'Viaje familiar'])->assertCreated();

        Sanctum::actingAs(Usuario::factory()->student()->create(), ['access-api']);
        $this->getJson($url.'/available-dates')->assertForbidden();
    }

    public function test_only_enrolled_students_can_send_notices(): void
    {
        Sanctum::actingAs(Usuario::factory()->student()->create(), ['access-api']);

        $this->postJson(self::STUDENT_API.$this->tutoring->getKey().'/absence-notices', ['date' => '2026-09-29', 'reason' => 'Cita médica'])
            ->assertForbidden();
    }
}

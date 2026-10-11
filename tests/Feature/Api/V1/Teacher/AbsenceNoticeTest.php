<?php

namespace Tests\Feature\Api\V1\Teacher;

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
        $this->putJson($this->path('/sessions'), ['date' => '2026-09-29', 'topics_covered' => false, 'attendance' => [
            ['enrollment_id' => $noticed->getKey(), 'present' => false],
            ['enrollment_id' => $other->getKey(), 'present' => true],
        ]])->assertOk();

        Sanctum::actingAs($student, ['access-api']);
        $this->getJson($url)->assertOk()->assertJsonPath('data.0.status', 'ausente');
    }

    public function test_only_enrolled_students_can_send_notices(): void
    {
        Sanctum::actingAs(Usuario::factory()->withRole('estudiante')->create(), ['access-api']);

        $this->postJson(self::STUDENT_API.$this->tutoring->getKey().'/absence-notices', ['date' => '2026-09-29', 'reason' => 'Cita médica'])
            ->assertForbidden();
    }
}

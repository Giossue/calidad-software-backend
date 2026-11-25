<?php

namespace Tests\Feature\Api\V1\Teacher;

use App\Models\InscripcionTutoria;
use App\Notifications\ActionNotification;
use Illuminate\Support\Facades\Notification;

class FinalResultNotificationTest extends TeacherTestCase
{
    private function record(InscripcionTutoria $enrollment, ?string $result): void
    {
        $this->putJson($this->path("/students/{$enrollment->getKey()}/final-result"), ['result' => $result])->assertOk();
    }

    /** @return array<int, ActionNotification> */
    private function finalResultNotices(InscripcionTutoria $enrollment): array
    {
        return Notification::sent($enrollment->estudiante, ActionNotification::class)
            ->filter(fn (ActionNotification $notice): bool => $notice->kind === 'tutoring_final_result')
            ->values()->all();
    }

    public function test_the_student_is_notified_when_the_teacher_approves_them(): void
    {
        $enrollment = $this->enrollment();

        $this->record($enrollment, InscripcionTutoria::APPROVED);

        $notices = $this->finalResultNotices($enrollment);
        $this->assertCount(1, $notices);
        $this->assertSame("Aprobaste la tutoría de {$this->tutoring->nombre}", $notices[0]->body);
        $this->assertSame('/panel/student-tutorings?tutoring='.$this->tutoring->getKey(), $notices[0]->url);
        Notification::assertNotSentTo($this->teacher, ActionNotification::class, fn (ActionNotification $notice): bool => $notice->kind === 'tutoring_final_result');
    }

    public function test_the_student_is_notified_when_the_teacher_fails_them(): void
    {
        $enrollment = $this->enrollment();

        $this->record($enrollment, InscripcionTutoria::FAILED);

        $notices = $this->finalResultNotices($enrollment);
        $this->assertCount(1, $notices);
        $this->assertSame("Reprobaste la tutoría de {$this->tutoring->nombre}", $notices[0]->body);
    }

    public function test_clearing_or_saving_the_same_result_again_sends_nothing_new(): void
    {
        $enrollment = $this->enrollment();

        $this->record($enrollment, null);
        $this->assertSame([], $this->finalResultNotices($enrollment));

        $this->record($enrollment, InscripcionTutoria::FAILED);
        $this->record($enrollment, InscripcionTutoria::FAILED);
        $this->record($enrollment, null);
        $this->assertCount(1, $this->finalResultNotices($enrollment));

        // Cambiar el resultado sí avisa del nuevo estado.
        $this->record($enrollment, InscripcionTutoria::FAILED);
        $this->record($enrollment, InscripcionTutoria::APPROVED);
        $bodies = array_map(fn (ActionNotification $notice): string => $notice->body, $this->finalResultNotices($enrollment));
        $this->assertSame([
            "Reprobaste la tutoría de {$this->tutoring->nombre}",
            "Reprobaste la tutoría de {$this->tutoring->nombre}",
            "Aprobaste la tutoría de {$this->tutoring->nombre}",
        ], $bodies);
    }
}

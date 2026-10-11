<?php

namespace Tests\Feature\Api\V1\Tutoring;

use App\Models\InscripcionTutoria;
use App\Models\Nota;
use App\Models\Usuario;
use Laravel\Sanctum\Sanctum;

class TutoringNotificationsTest extends TutoringTestCase
{
    public function test_assigning_a_teacher_enrolling_students_and_grading_notify_those_affected(): void
    {
        $tutoring = $this->createTutoring();
        $teacher = Usuario::factory()->withRole('docente')->create();
        $student = Usuario::factory()->withRole('estudiante')->create();
        $other = Usuario::factory()->withRole('estudiante')->create();

        $tutoring->update(['fk_docente' => $teacher->getKey()]);
        $this->assertSame(['Se te asignó una tutoría'], $this->titles($teacher));

        foreach ([$student, $other] as $enrolled) {
            InscripcionTutoria::query()->create([
                'fk_asig_tutoria' => $tutoring->getKey(), 'fk_id_usuario' => $enrolled->getKey(),
                'fecha_inscripcion' => '2026-09-01', 'estado' => true,
            ]);
        }

        $this->assertSame(['Fuiste inscrito en una tutoría'], $this->titles($student));
        $this->assertContains('Nuevos estudiantes en tu tutoría', $this->titles($teacher));

        $enrollment = InscripcionTutoria::query()->where('fk_id_usuario', $student->getKey())->firstOrFail();
        Nota::query()->create(['fk_inscripcion' => $enrollment->getKey(), 'tipo' => 'diagnostico', 'valor' => 8, 'fecha_registro' => '2026-09-10']);
        $this->assertContains('Calificaciones actualizadas', $this->titles($student));
        $this->assertNotContains('Calificaciones actualizadas', $this->titles($other));
    }

    public function test_schedule_changes_notify_students_and_teacher_once_per_request(): void
    {
        $tutoring = $this->createTutoring();
        $teacher = Usuario::factory()->withRole('docente')->create();
        $student = Usuario::factory()->withRole('estudiante')->create();
        $tutoring->update(['fk_docente' => $teacher->getKey()]);
        InscripcionTutoria::query()->create([
            'fk_asig_tutoria' => $tutoring->getKey(), 'fk_id_usuario' => $student->getKey(),
            'fecha_inscripcion' => '2026-09-01', 'estado' => true,
        ]);

        Sanctum::actingAs($this->coordinator, ['*']);
        $this->postJson(self::API.'/tutorings/'.$tutoring->getKey().'/schedules', [
            'day' => 'lunes', 'start_time' => '10:00', 'end_time' => '11:00', 'room' => 'Aula 101',
        ])->assertCreated();

        $this->assertSame(1, collect($this->titles($student))->filter(fn ($title) => $title === 'Cambio de horario')->count());
        $this->assertContains('Cambio de horario', $this->titles($teacher));
        $this->assertNotContains('Cambio de horario', $this->titles($this->coordinator));
    }

    /** @return array<int, string> */
    private function titles(Usuario $user): array
    {
        return $user->notifications()->get()->pluck('data.title')->all();
    }
}

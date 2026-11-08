<?php

namespace Tests\Feature\Api\V1\Teacher;

use App\Models\AsignaturaTutoria;
use App\Models\InscripcionTutoria;
use App\Models\Nota;
use App\Models\PeriodoAcademico;
use App\Models\Usuario;
use Laravel\Sanctum\Sanctum;

class FinalResultTest extends TeacherTestCase
{
    private function resultPath(InscripcionTutoria $enrollment): string
    {
        return $this->path("/students/{$enrollment->getKey()}/final-result");
    }

    /** Tutoría ya creada en un período posterior de la asignatura que continúa. */
    private function nextTutoring(): AsignaturaTutoria
    {
        $next = $this->createSubject($this->createCycle($this->career, null, 2), 'CS-201');
        $next->update(['name' => 'Calidad de software II']);
        $this->tutoring->subject->nextSubjects()->attach($next);
        $later = PeriodoAcademico::query()->create(['nombre' => 'PAO 2027', 'fecha_inicio' => '2026-11-01', 'fecha_fin' => '2027-03-31', 'estado' => false]);

        return AsignaturaTutoria::query()->create([
            'subject_id' => $next->getKey(), 'fk_ciclo' => $this->cycle->getKey(), 'fk_periodo' => $later->getKey(),
            'fk_modalidad' => $this->modality->getKey(), 'fk_paralelo' => $this->tutoring->fk_paralelo, 'nombre' => $next->name, 'estado' => true,
        ]);
    }

    public function test_teacher_records_the_final_result_with_a_suggestion_from_the_partials(): void
    {
        $enrollment = $this->enrollment();
        Nota::query()->create(['fk_inscripcion' => $enrollment->getKey(), 'tipo' => 'partial', 'valor' => 8, 'fecha_registro' => today()]);
        Nota::query()->create(['fk_inscripcion' => $enrollment->getKey(), 'tipo' => 'partial_two', 'valor' => 6.5, 'fecha_registro' => today()]);

        $this->getJson($this->path('/students'))->assertOk()
            ->assertJsonPath('data.0.final_result', null)
            ->assertJsonPath('data.0.suggested_result', InscripcionTutoria::APPROVED);

        $this->putJson($this->resultPath($enrollment), ['result' => InscripcionTutoria::FAILED])->assertOk()->assertJsonPath('data.final_result', 'failed');
        $this->assertNotNull($enrollment->refresh()->final_result_at);
        $this->putJson($this->resultPath($enrollment), ['result' => null])->assertOk()->assertJsonPath('data.final_result', null);
        $this->assertNull($enrollment->refresh()->final_result_at);

        $this->putJson($this->resultPath($enrollment), ['result' => 'aprobado'])->assertUnprocessable()->assertJsonValidationErrors('result');
        $this->putJson($this->resultPath($enrollment), [])->assertUnprocessable()->assertJsonValidationErrors('result');
    }

    public function test_an_approved_student_is_enrolled_in_the_next_tutoring_that_already_exists(): void
    {
        $next = $this->nextTutoring();
        $approved = $this->enrollment();
        $failed = $this->enrollment();

        $this->putJson($this->resultPath($approved), ['result' => InscripcionTutoria::APPROVED])->assertOk();
        $this->putJson($this->resultPath($failed), ['result' => InscripcionTutoria::FAILED])->assertOk();
        // Volver a aprobar no duplica la inscripción.
        $this->putJson($this->resultPath($approved), ['result' => InscripcionTutoria::APPROVED])->assertOk();

        $this->assertSame([$approved->fk_id_usuario], InscripcionTutoria::query()->where('fk_asig_tutoria', $next->getKey())->pluck('fk_id_usuario')->all());
    }

    public function test_approving_a_pending_subject_stops_blocking_the_ones_that_continue_it(): void
    {
        $next = $this->nextTutoring();
        $enrollment = $this->enrollment();
        $student = $enrollment->estudiante;
        $student->pendingSubjects()->attach($this->tutoring->subject_id);

        $this->putJson($this->resultPath($enrollment), ['result' => InscripcionTutoria::APPROVED])->assertOk();

        $this->assertSame([], $student->pendingSubjects()->pluck('subjects.id')->all());
        $this->assertTrue(InscripcionTutoria::query()->where('fk_asig_tutoria', $next->getKey())->where('fk_id_usuario', $student->getKey())->exists());
    }

    public function test_only_the_assigned_teacher_records_results_of_active_enrollments(): void
    {
        $enrollment = $this->enrollment();
        $enrollment->update(['estado' => false]);
        $this->putJson($this->resultPath($enrollment), ['result' => InscripcionTutoria::APPROVED])->assertUnprocessable()->assertJsonValidationErrors('result');

        $enrollment->update(['estado' => true]);
        Sanctum::actingAs(Usuario::factory()->withRole('docente')->create(), ['access-api']);
        $this->putJson($this->resultPath($enrollment), ['result' => InscripcionTutoria::APPROVED])->assertForbidden();
        $this->assertNull($enrollment->refresh()->final_result);
    }
}

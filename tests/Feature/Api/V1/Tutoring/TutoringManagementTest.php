<?php

namespace Tests\Feature\Api\V1\Tutoring;

use App\Models\AsignaturaTutoria;
use App\Models\InscripcionTutoria;
use App\Models\Modalidad;
use App\Models\PeriodoAcademico;
use App\Models\Usuario;
use Laravel\Sanctum\Sanctum;

class TutoringManagementTest extends TutoringTestCase
{
    public function test_coordinator_can_create_and_edit_a_tutoring_and_assign_another_compatible_cycle(): void
    {
        $subject = $this->createSubject();
        Sanctum::actingAs($this->coordinator, ['*']);
        $response = $this->postJson(self::API.'/tutorings', $this->tutoringPayload($subject))->assertCreated()
            ->assertJsonPath('data.subject_id', $subject->getKey())
            ->assertJsonPath('data.subject_name', $subject->name)
            ->assertJsonPath('data.section_id', $this->cycle->fk_paralelo)->assertJsonPath('data.teacher_id', null);
        $url = self::API.'/tutorings/'.$response->json('data.id');
        $this->assertDatabaseHas('ciclo_periodo', ['fk_ciclo' => $this->cycle->getKey(), 'fk_periodo' => $this->period->getKey(), 'estado' => true]);
        $modality = Modalidad::query()->create(['nombre' => 'Virtual', 'estado' => true]);
        $this->patchJson($url, ['modality_id' => $modality->getKey()])->assertOk()->assertJsonPath('data.modality_id', $modality->getKey());
        $cycle = $this->createCycle($this->career, number: 2);
        $subject->cycles()->attach($cycle);
        $this->putJson($url.'/cycle', ['cycle_id' => $cycle->getKey()])->assertOk()
            ->assertJsonPath('data.cycle_id', $cycle->getKey())->assertJsonPath('data.section_id', $cycle->fk_paralelo);
        $this->assertDatabaseHas('ciclo_periodo', ['fk_ciclo' => $cycle->getKey(), 'fk_periodo' => $this->period->getKey(), 'estado' => true]);
    }

    public function test_tutoring_creation_requires_subject_linked_to_an_active_compatible_cycle(): void
    {
        $subject = $this->createSubject();
        Sanctum::actingAs($this->coordinator, ['*']);
        $payload = $this->tutoringPayload($subject);
        $subject->cycles()->detach();
        $this->postJson(self::API.'/tutorings', $payload)->assertUnprocessable()->assertJsonValidationErrors('subject_id');
        $subject->cycles()->attach($this->cycle);
        $subject->update(['is_active' => false]);
        $this->postJson(self::API.'/tutorings', $payload)->assertUnprocessable()->assertJsonValidationErrors('subject_id');
        $subject->update(['is_active' => true]);
        $this->cycle->update(['estado' => false]);
        $this->postJson(self::API.'/tutorings', $payload)->assertUnprocessable()->assertJsonValidationErrors('cycle_id');
        $this->cycle->update(['estado' => true]);
        $this->career->update(['estado' => false]);
        $this->postJson(self::API.'/tutorings', $payload)->assertUnprocessable()->assertJsonValidationErrors('cycle_id');
        $this->career->update(['estado' => true]);
        $foreignSubject = $this->createSubject($this->otherCycle);
        $this->postJson(self::API.'/tutorings', $this->tutoringPayload($foreignSubject))->assertUnprocessable()->assertJsonValidationErrors('cycle_id');
        $this->assertDatabaseCount('asignatura_tutoria', 0);
    }

    public function test_tutoring_requires_active_period_modality_and_section(): void
    {
        $subject = $this->createSubject();
        Sanctum::actingAs($this->coordinator, ['*']);
        $payload = $this->tutoringPayload($subject);
        $this->period->update(['estado' => false]);
        $this->postJson(self::API.'/tutorings', $payload)->assertUnprocessable()->assertJsonValidationErrors('period_id');
        $this->period->update(['estado' => true]);
        $this->modality->update(['estado' => false]);
        $this->postJson(self::API.'/tutorings', $payload)->assertUnprocessable()->assertJsonValidationErrors('modality_id');
        $this->modality->update(['estado' => true]);
        $this->cycle->paralelo->update(['estado' => false]);
        $this->postJson(self::API.'/tutorings', $payload)->assertUnprocessable()->assertJsonValidationErrors('cycle_id');
        $this->assertDatabaseCount('asignatura_tutoria', 0);
    }

    public function test_duplicate_tutoring_is_rejected_even_after_logical_deactivation(): void
    {
        $subject = $this->createSubject();
        Sanctum::actingAs($this->coordinator, ['*']);
        $payload = $this->tutoringPayload($subject);
        $id = $this->postJson(self::API.'/tutorings', $payload)->assertCreated()->json('data.id');
        $this->postJson(self::API.'/tutorings', $payload)->assertUnprocessable()->assertJsonValidationErrors('subject_id');
        $this->patchJson(self::API.'/tutorings/'.$id.'/deactivate')->assertOk()->assertJsonPath('data.is_active', false);
        $this->postJson(self::API.'/tutorings', $payload)->assertUnprocessable()->assertJsonValidationErrors('subject_id');
        $this->assertDatabaseCount('asignatura_tutoria', 1);
    }

    public function test_coordinator_can_reactivate_a_tutoring_keeping_teacher_and_enrollments(): void
    {
        $tutoring = $this->createTutoring();
        $teacher = Usuario::factory()->withRole('docente')->create();
        $tutoring->update(['fk_docente' => $teacher->getKey()]);
        $student = Usuario::factory()->withRole('estudiante')->create();
        InscripcionTutoria::query()->create(['fk_asig_tutoria' => $tutoring->getKey(), 'fk_id_usuario' => $student->getKey(), 'fecha_inscripcion' => now()->toDateString(), 'estado' => true]);
        Sanctum::actingAs($this->coordinator, ['*']);
        $url = self::API.'/tutorings/'.$tutoring->getKey();

        $this->patchJson($url.'/deactivate')->assertOk()->assertJsonPath('data.is_active', false);
        $this->patchJson($url.'/activate')->assertOk()->assertJsonPath('data.is_active', true)->assertJsonPath('data.teacher_id', $teacher->getKey());
        $this->patchJson($url.'/activate')->assertOk()->assertJsonPath('data.is_active', true);
        $this->assertDatabaseHas('inscripcion_tutoria', ['fk_asig_tutoria' => $tutoring->getKey(), 'fk_id_usuario' => $student->getKey(), 'estado' => true]);
    }

    public function test_tutoring_cannot_be_reactivated_with_inactive_period_or_cycle(): void
    {
        $tutoring = $this->createTutoring();
        $tutoring->update(['estado' => false]);
        Sanctum::actingAs($this->coordinator, ['*']);
        $url = self::API.'/tutorings/'.$tutoring->getKey().'/activate';

        $this->period->update(['estado' => false]);
        $this->patchJson($url)->assertUnprocessable()->assertJsonValidationErrors('period_id');
        $this->period->update(['estado' => true]);
        $this->cycle->update(['estado' => false]);
        $this->patchJson($url)->assertUnprocessable()->assertJsonValidationErrors('cycle_id');
        $this->assertFalse($tutoring->refresh()->estado);
    }

    public function test_tutoring_listing_and_mutations_are_limited_to_coordinated_careers(): void
    {
        $own = $this->createTutoring();
        $foreign = $this->createTutoring($this->otherCycle);
        Sanctum::actingAs($this->coordinator, ['*']);
        $this->getJson(self::API.'/tutorings')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->getKey());
        $this->getJson(self::API.'/tutorings?career_id='.$this->otherCareer->getKey())->assertOk()->assertJsonCount(0, 'data');
        $this->postJson(self::API.'/tutorings', $this->tutoringPayload($foreign->subject, $this->otherCycle))->assertForbidden();
        $url = self::API.'/tutorings/'.$foreign->getKey();
        $this->patchJson($url, ['modality_id' => $this->modality->getKey()])->assertForbidden();
        $this->patchJson($url.'/deactivate')->assertForbidden();
        $this->patchJson($url.'/activate')->assertForbidden();
        $this->putJson($url.'/cycle', ['cycle_id' => $this->cycle->getKey()])->assertForbidden();
        $teacher = Usuario::factory()->withRole('docente')->create();
        $this->putJson($url.'/teacher', ['teacher_id' => $teacher->getKey()])->assertForbidden();
        $this->assertNull($foreign->refresh()->fk_docente);
    }

    public function test_tutoring_listing_filters_by_cycle_and_status(): void
    {
        $active = $this->createTutoring();
        $secondCycle = $this->createCycle($this->career, number: 2);
        $inactive = $this->createTutoring($secondCycle, $this->createSubject($secondCycle, 'CS-102'));
        $inactive->update(['estado' => false]);
        Sanctum::actingAs($this->coordinator, ['*']);

        $this->getJson(self::API.'/tutorings?cycle_id='.$this->cycle->getKey())->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->getKey());
        $this->getJson(self::API.'/tutorings?status=active')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->getKey());
        $this->getJson(self::API.'/tutorings?status=inactive')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $inactive->getKey());
        $this->getJson(self::API.'/tutorings?cycle_id='.$secondCycle->getKey().'&status=active')->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_cycle_reassignment_requires_the_same_career_and_subject_link(): void
    {
        $tutoring = $this->createTutoring();
        Sanctum::actingAs($this->coordinator, ['*']);
        $url = self::API.'/tutorings/'.$tutoring->getKey().'/cycle';
        $this->putJson($url, ['cycle_id' => $this->otherCycle->getKey()])->assertUnprocessable()->assertJsonValidationErrors('cycle_id');
        $unlinkedCycle = $this->createCycle($this->career, number: 2);
        $this->putJson($url, ['cycle_id' => $unlinkedCycle->getKey()])->assertUnprocessable()->assertJsonValidationErrors('subject_id');
        $this->assertSame($this->cycle->getKey(), $tutoring->refresh()->fk_ciclo);
    }

    public function test_teacher_assignment_accepts_only_active_teachers_without_granting_user_management_access(): void
    {
        $tutoring = $this->createTutoring();
        $inactive = Usuario::factory()->withRole('docente')->create(['estado' => false]);
        $student = Usuario::factory()->withRole('estudiante')->create();
        $teacher = Usuario::factory()->withRole('docente')->create();
        Sanctum::actingAs($this->coordinator, ['*']);
        $this->getJson(self::API.'/available-teachers')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $teacher->getKey());
        $url = self::API.'/tutorings/'.$tutoring->getKey().'/teacher';
        foreach ([$inactive, $student] as $invalidTeacher) {
            $this->putJson($url, ['teacher_id' => $invalidTeacher->getKey()])->assertUnprocessable()->assertJsonValidationErrors('teacher_id');
        }
        $this->putJson($url, ['teacher_id' => $teacher->getKey()])->assertOk()
            ->assertJsonPath('data.teacher_id', $teacher->getKey())->assertJsonPath('data.teacher_name', $teacher->nombre);
        $this->assertDatabaseMissing('career_teacher', ['user_id' => $teacher->getKey(), 'career_id' => $this->career->getKey()]);
        $this->patchJson(self::API.'/teachers/'.$teacher->getKey().'/deactivate')->assertForbidden();
    }

    public function test_tutoring_update_cannot_bypass_subject_cycle_or_teacher_assignment_rules(): void
    {
        $tutoring = $this->createTutoring();
        $student = Usuario::factory()->withRole('estudiante')->create();
        Sanctum::actingAs($this->coordinator, ['*']);
        $url = self::API.'/tutorings/'.$tutoring->getKey();
        $this->patchJson($url, ['subject_id' => 999999, 'cycle_id' => $this->otherCycle->getKey()])
            ->assertUnprocessable()->assertJsonValidationErrors(['subject_id', 'cycle_id']);
        $this->patchJson($url, ['teacher_id' => $student->getKey()])->assertOk();
        $this->assertNull(AsignaturaTutoria::query()->findOrFail($tutoring->getKey())->fk_docente);
    }

    public function test_enrolled_tutoring_cannot_move_cycle_or_academic_period(): void
    {
        $tutoring = $this->createTutoring();
        $student = Usuario::factory()->withRole('estudiante')->create();
        $enrollment = InscripcionTutoria::query()->create([
            'fk_asig_tutoria' => $tutoring->getKey(), 'fk_id_usuario' => $student->getKey(),
            'fecha_inscripcion' => '2026-09-01', 'estado' => true,
        ]);
        $cycle = $this->createCycle($this->career, number: 2);
        $tutoring->subject->cycles()->attach($cycle);
        $period = PeriodoAcademico::query()->create([
            'nombre' => 'PAO 2027', 'fecha_inicio' => '2027-01-01', 'fecha_fin' => '2027-06-30', 'estado' => true,
        ]);
        Sanctum::actingAs($this->coordinator, ['*']);
        $url = self::API.'/tutorings/'.$tutoring->getKey();
        $this->putJson($url.'/cycle', ['cycle_id' => $cycle->getKey()])->assertUnprocessable()->assertJsonValidationErrors('cycle_id');
        $this->patchJson($url, ['period_id' => $period->getKey()])->assertUnprocessable()->assertJsonValidationErrors('period_id');
        $enrollment->update(['estado' => false]);
        $this->putJson($url.'/cycle', ['cycle_id' => $cycle->getKey()])->assertUnprocessable();
        $this->patchJson($url, ['period_id' => $period->getKey()])->assertUnprocessable();
        $this->assertSame($this->cycle->getKey(), $tutoring->refresh()->fk_ciclo);
        $this->assertSame($this->period->getKey(), $tutoring->fk_periodo);
    }

    public function test_historical_tutoring_without_subject_can_move_to_an_active_cycle_in_its_career(): void
    {
        $tutoring = $this->createTutoring();
        $tutoring->update(['subject_id' => null]);
        $cycle = $this->createCycle($this->career, number: 2);
        Sanctum::actingAs($this->coordinator, ['*']);
        $url = self::API.'/tutorings/'.$tutoring->getKey().'/cycle';
        $this->putJson($url, ['cycle_id' => $this->otherCycle->getKey()])->assertUnprocessable();
        $this->putJson($url, ['cycle_id' => $cycle->getKey()])->assertOk()
            ->assertJsonPath('data.cycle_id', $cycle->getKey())->assertJsonPath('data.subject_id', null);
    }

    public function test_inactive_tutoring_cannot_receive_a_new_cycle_or_teacher(): void
    {
        $tutoring = $this->createTutoring();
        $tutoring->update(['estado' => false]);
        $teacher = Usuario::factory()->withRole('docente')->create();
        Sanctum::actingAs($this->coordinator, ['*']);
        $url = self::API.'/tutorings/'.$tutoring->getKey();
        $this->putJson($url.'/teacher', ['teacher_id' => $teacher->getKey()])->assertUnprocessable()->assertJsonValidationErrors('tutoring_id');
        $this->putJson($url.'/cycle', ['cycle_id' => $this->cycle->getKey()])->assertUnprocessable()->assertJsonValidationErrors('tutoring_id');
        $this->assertNull($tutoring->refresh()->fk_docente);
    }

    public function test_update_preserves_current_inactive_period_and_modality_but_rejects_new_inactive_options(): void
    {
        $tutoring = $this->createTutoring();
        $otherTutoring = $this->createTutoring(subject: $this->createSubject(code: 'CS-102'));
        $this->period->update(['estado' => false]);
        $this->modality->update(['estado' => false]);
        $otherPeriod = PeriodoAcademico::query()->create([
            'nombre' => 'PAO 2027', 'fecha_inicio' => '2027-01-01', 'fecha_fin' => '2027-06-30', 'estado' => false,
        ]);
        $otherModality = Modalidad::query()->create(['nombre' => 'Virtual', 'estado' => false]);
        Sanctum::actingAs($this->coordinator, ['*']);
        $url = self::API.'/tutorings/'.$tutoring->getKey();
        $this->patchJson($url, [
            'period_id' => $this->period->getKey(), 'modality_id' => $this->modality->getKey(),
        ])->assertOk()->assertJsonPath('data.period_id', $this->period->getKey())
            ->assertJsonPath('data.modality_id', $this->modality->getKey());
        $this->patchJson($url, ['period_id' => $otherPeriod->getKey()])->assertUnprocessable()->assertJsonValidationErrors('period_id');
        $this->patchJson($url, ['modality_id' => $otherModality->getKey()])->assertUnprocessable()->assertJsonValidationErrors('modality_id');
        $this->assertSame($this->period->getKey(), $tutoring->refresh()->fk_periodo);
        $this->assertSame($this->modality->getKey(), $tutoring->fk_modalidad);

        $otherPeriod->update(['estado' => true]);
        $otherModality->update(['estado' => true]);
        $this->patchJson($url, ['modality_id' => $otherModality->getKey()])->assertOk()
            ->assertJsonPath('data.modality_id', $otherModality->getKey())
            ->assertJsonPath('data.period_id', $this->period->getKey());
        $this->patchJson(self::API.'/tutorings/'.$otherTutoring->getKey(), ['period_id' => $otherPeriod->getKey()])->assertOk()
            ->assertJsonPath('data.period_id', $otherPeriod->getKey())
            ->assertJsonPath('data.modality_id', $this->modality->getKey());
    }
}

<?php

namespace Tests\Feature\Api\V1\Tutoring;

use App\Models\Ciclo;
use App\Models\Paralelo;
use App\Models\Role;
use App\Models\Subject;
use App\Models\Usuario;
use App\Notifications\ProvisionalPasswordNotification;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

class SubjectAndTeacherTest extends TutoringTestCase
{
    public function test_coordinator_can_create_edit_assign_and_deactivate_subject_without_removing_history(): void
    {
        Sanctum::actingAs($this->coordinator, ['*']);
        $response = $this->postJson(self::API.'/subjects', [
            'career_id' => $this->career->getKey(), 'code' => 'CS-101', 'name' => 'Calidad',
        ])->assertCreated()->assertJsonPath('data.is_active', true)->assertJsonPath('data.cycle_ids', []);
        $subject = Subject::query()->findOrFail($response->json('data.id'));
        $url = self::API.'/subjects/'.$subject->getKey();
        $this->patchJson($url, ['name' => 'Calidad de software', 'code' => 'CS-102'])->assertOk()
            ->assertJsonPath('data.name', 'calidad de software')->assertJsonPath('data.code', 'CS-102');
        foreach ([1, 2] as $attempt) {
            $this->putJson($url.'/cycles/'.$this->cycle->getKey())->assertOk()
                ->assertJsonPath('data.cycle_ids', [$this->cycle->getKey()]);
        }
        $this->assertDatabaseCount('subject_cycle', 1);
        $tutoring = $this->createTutoring(subject: $subject->refresh());

        $this->patchJson($url.'/deactivate')->assertOk()->assertJsonPath('data.is_active', false);
        $this->assertDatabaseHas('subjects', ['id' => $subject->getKey(), 'is_active' => false]);
        $this->assertDatabaseHas('asignatura_tutoria', ['id_asig_tutoria' => $tutoring->getKey(), 'subject_id' => $subject->getKey()]);
        $this->assertDatabaseCount('subject_cycle', 1);
    }

    public function test_coordinator_can_unassign_a_cycle_but_not_while_an_active_tutoring_uses_it(): void
    {
        $subject = $this->createSubject();
        $secondCycle = $this->createCycle($this->career, number: 2);
        Sanctum::actingAs($this->coordinator, ['*']);
        $this->putJson(self::API.'/subjects/'.$subject->getKey().'/cycles/'.$secondCycle->getKey())->assertOk()
            ->assertJsonPath('data.cycle_ids', [$this->cycle->getKey(), $secondCycle->getKey()]);

        $tutoring = $this->createTutoring(subject: $subject->refresh());

        $this->deleteJson(self::API.'/subjects/'.$subject->getKey().'/cycles/'.$this->cycle->getKey())
            ->assertUnprocessable()->assertJsonValidationErrors('cycle_id');
        $this->assertDatabaseHas('subject_cycle', ['subject_id' => $subject->getKey(), 'cycle_id' => $this->cycle->getKey()]);

        $this->deleteJson(self::API.'/subjects/'.$subject->getKey().'/cycles/'.$secondCycle->getKey())->assertOk()
            ->assertJsonPath('data.cycle_ids', [$this->cycle->getKey()]);
        $this->assertDatabaseMissing('subject_cycle', ['subject_id' => $subject->getKey(), 'cycle_id' => $secondCycle->getKey()]);

        $tutoring->update(['estado' => false]);
        $this->deleteJson(self::API.'/subjects/'.$subject->getKey().'/cycles/'.$this->cycle->getKey())->assertOk()
            ->assertJsonPath('data.cycle_ids', []);
    }

    public function test_subject_listing_filters_by_cycle_and_status(): void
    {
        $active = $this->createSubject(code: 'CS-101');
        $inactive = $this->createSubject(code: 'CS-102');
        $inactive->update(['is_active' => false]);
        $otherCycleSubject = $this->createSubject($this->createCycle($this->career, number: 2), code: 'CS-103');
        Sanctum::actingAs($this->coordinator, ['*']);

        $this->getJson(self::API.'/subjects?cycle_id='.$this->cycle->getKey())->assertOk()->assertJsonCount(2, 'data');
        $this->getJson(self::API.'/subjects?status=active')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonMissing(['id' => $inactive->getKey()]);
        $this->getJson(self::API.'/subjects?status=inactive')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $inactive->getKey());
        $this->getJson(self::API.'/subjects?cycle_id='.$this->cycle->getKey().'&status=active')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $active->getKey());
    }

    public function test_subject_listing_and_mutations_are_limited_to_coordinated_careers(): void
    {
        $own = $this->createSubject();
        $foreign = $this->createSubject($this->otherCycle);
        Sanctum::actingAs($this->coordinator, ['*']);
        $this->getJson(self::API.'/subjects')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->getKey());
        $this->getJson(self::API.'/subjects?career_id='.$this->otherCareer->getKey())->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(self::API.'/subjects?search=CS-101')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson(self::API.'/subjects?search=inexistente')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson(self::API.'/subjects', [
            'career_id' => $this->otherCareer->getKey(), 'code' => 'CS-102', 'name' => 'Álgebra',
        ])->assertForbidden();
        $url = self::API.'/subjects/'.$foreign->getKey();
        $this->patchJson($url, ['name' => 'Otra'])->assertForbidden();
        $this->patchJson($url.'/deactivate')->assertForbidden();
        $this->putJson($url.'/cycles/'.$this->otherCycle->getKey())->assertForbidden();
        $this->deleteJson($url.'/cycles/'.$this->otherCycle->getKey())->assertForbidden();
        $this->assertSame('Calidad de software', $foreign->refresh()->name);
    }

    public function test_subject_codes_are_unique_per_career_and_career_cannot_be_changed(): void
    {
        $subject = $this->createSubject();
        Sanctum::actingAs($this->coordinator, ['*']);
        $payload = ['career_id' => $this->career->getKey(), 'code' => $subject->code, 'name' => 'Duplicada'];
        $this->postJson(self::API.'/subjects', $payload)->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->patchJson(self::API.'/subjects/'.$subject->getKey(), ['code' => $subject->code])->assertOk();
        $this->patchJson(self::API.'/subjects/'.$subject->getKey(), ['career_id' => $this->otherCareer->getKey()])
            ->assertUnprocessable()->assertJsonValidationErrors('career_id');
        $this->coordinator->coordinatedCareers()->attach($this->otherCareer);
        $this->postJson(self::API.'/subjects', array_replace($payload, ['career_id' => $this->otherCareer->getKey()]))->assertCreated();
    }

    public function test_subject_can_be_created_with_its_cycle_in_one_step(): void
    {
        Sanctum::actingAs($this->coordinator, ['*']);
        $payload = ['career_id' => $this->career->getKey(), 'code' => 'SW-001', 'name' => 'Algoritmos', 'cycle_id' => $this->cycle->getKey()];

        $this->postJson(self::API.'/subjects', $payload)->assertCreated()
            ->assertJsonPath('data.cycle_ids', [$this->cycle->getKey()]);

        $this->postJson(self::API.'/subjects', array_replace($payload, ['code' => 'SW-002', 'cycle_id' => $this->otherCycle->getKey()]))
            ->assertUnprocessable()->assertJsonValidationErrors('cycle_id');
        $this->assertDatabaseMissing('subjects', ['code' => 'SW-002']);
    }

    public function test_subject_assignment_rejects_inactive_and_incompatible_cycles(): void
    {
        $subject = $this->createSubject();
        Sanctum::actingAs($this->coordinator, ['*']);
        $url = self::API.'/subjects/'.$subject->getKey().'/cycles/';
        $this->putJson($url.$this->otherCycle->getKey())->assertUnprocessable()->assertJsonValidationErrors('cycle_id');
        $this->cycle->update(['estado' => false]);
        $this->putJson($url.$this->cycle->getKey())->assertUnprocessable();
        $this->cycle->update(['estado' => true]);
        $subject->update(['is_active' => false]);
        $this->putJson($url.$this->cycle->getKey())->assertUnprocessable();
    }

    public function test_coordinator_can_link_an_existing_teacher_to_their_careers_only(): void
    {
        $teacher = Usuario::factory()->withRole('docente')->create();
        $inactive = Usuario::factory()->withRole('docente')->create(['estado' => false]);
        $student = Usuario::factory()->withRole('estudiante')->create();
        Sanctum::actingAs($this->coordinator, ['*']);

        $this->getJson(self::API.'/available-teachers?exclude_career_id='.$this->career->getKey())
            ->assertOk()->assertJsonFragment(['id' => $teacher->getKey()]);

        $teacher->update(['nombre' => 'Ana Torres']);
        $this->getJson(self::API.'/available-teachers?search=torres+ana')
            ->assertOk()->assertJsonFragment(['id' => $teacher->getKey()]);
        $this->getJson(self::API.'/available-teachers?search=torres+pedro')
            ->assertOk()->assertJsonCount(0, 'data');

        $url = self::API.'/teachers/'.$teacher->getKey().'/careers';
        $this->postJson($url, ['career_id' => $this->career->getKey()])
            ->assertOk()->assertJsonPath('data.career_ids', [$this->career->getKey()]);
        $this->postJson($url, ['career_id' => $this->career->getKey()])->assertOk();
        $this->assertSame(1, $teacher->teachingCareers()->count());

        $this->getJson(self::API.'/available-teachers?exclude_career_id='.$this->career->getKey())
            ->assertOk()->assertJsonMissing(['id' => $teacher->getKey()]);

        $this->postJson($url, ['career_id' => $this->otherCareer->getKey()])->assertForbidden();
        $this->postJson(self::API.'/teachers/'.$inactive->getKey().'/careers', ['career_id' => $this->career->getKey()])->assertUnprocessable();
        $this->postJson(self::API.'/teachers/'.$student->getKey().'/careers', ['career_id' => $this->career->getKey()])->assertUnprocessable();

        $this->coordinator->coordinatedCareers()->attach($this->otherCareer);
        $this->postJson($url, ['career_id' => $this->otherCareer->getKey()])
            ->assertOk()->assertJsonCount(2, 'data.career_ids');
    }

    public function test_coordinator_can_unlink_a_teacher_from_a_career_unless_it_has_active_tutorings(): void
    {
        $teacher = Usuario::factory()->withRole('docente')->create();
        $teacher->teachingCareers()->attach([$this->career->getKey(), $this->otherCareer->getKey()]);
        $tutoring = $this->createTutoring();
        $tutoring->update(['fk_docente' => $teacher->getKey()]);
        Sanctum::actingAs($this->coordinator, ['*']);
        $url = self::API.'/teachers/'.$teacher->getKey().'/careers/';

        $this->deleteJson($url.$this->career->getKey())->assertUnprocessable()->assertJsonValidationErrors('career');

        $tutoring->update(['estado' => false]);
        $this->deleteJson($url.$this->otherCareer->getKey())->assertForbidden();
        $this->deleteJson($url.$this->career->getKey())
            ->assertOk()->assertJsonPath('data.career_ids', [$this->otherCareer->getKey()]);
        $this->assertSame(1, $teacher->teachingCareers()->count());
    }

    public function test_coordinator_can_create_and_edit_a_teacher_with_a_provisional_password_notification(): void
    {
        Notification::fake();
        Sanctum::actingAs($this->coordinator, ['*']);
        $response = $this->postJson(self::API.'/teachers', $this->teacherPayload())->assertCreated()
            ->assertJsonPath('data.career_ids', [$this->career->getKey()])->assertJsonPath('data.is_active', true);
        $teacher = Usuario::query()->findOrFail($response->json('data.id'));
        $this->assertTrue($teacher->hasRole('docente'));
        $this->assertFalse($teacher->hasRole('estudiante'));
        $this->assertNotEmpty($teacher->password_hash);
        $response->assertJsonMissingPath('data.password_hash')->assertJsonMissingPath('data.password');
        Notification::assertSentTo($teacher, ProvisionalPasswordNotification::class);
        $this->patchJson(self::API.'/teachers/'.$teacher->getKey(), [
            'name' => 'María Torres', 'email' => 'maria.torres@ueb.edu.ec',
        ])->assertOk()->assertJsonPath('data.name', 'María Torres')->assertJsonPath('data.email', 'maria.torres@ueb.edu.ec');
    }

    public function test_teacher_management_only_allows_teachers_linked_to_a_coordinated_career(): void
    {
        $own = Usuario::factory()->withRole('docente')->create();
        $own->teachingCareers()->attach($this->career);
        $foreign = Usuario::factory()->withRole('docente')->create();
        $foreign->teachingCareers()->attach($this->otherCareer);
        $student = Usuario::factory()->withRole('estudiante')->create();
        $student->teachingCareers()->attach($this->career);
        Sanctum::actingAs($this->coordinator, ['*']);
        $this->getJson(self::API.'/teachers')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->getKey());
        foreach ([$foreign, $student] as $user) {
            $url = self::API.'/teachers/'.$user->getKey();
            $this->patchJson($url, ['name' => 'Cambio indebido'])->assertForbidden();
            $this->patchJson($url.'/deactivate')->assertForbidden();
            $this->assertTrue($user->refresh()->estado);
        }
        $this->postJson(self::API.'/teachers', $this->teacherPayload($this->otherCareer))->assertForbidden();
    }

    public function test_teacher_listing_filters_by_status(): void
    {
        $active = Usuario::factory()->withRole('docente')->create();
        $active->teachingCareers()->attach($this->career);
        $inactive = Usuario::factory()->withRole('docente')->create(['estado' => false]);
        $inactive->teachingCareers()->attach($this->career);
        Sanctum::actingAs($this->coordinator, ['*']);

        $this->getJson(self::API.'/teachers?status=active')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->getKey());
        $this->getJson(self::API.'/teachers?status=inactive')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $inactive->getKey());
    }

    public function test_teacher_input_rejects_invalid_identity_email_and_role_escalation(): void
    {
        Notification::fake();
        Sanctum::actingAs($this->coordinator, ['*']);
        $payload = $this->teacherPayload();
        $this->postJson(self::API.'/teachers', array_replace($payload, [
            'identification' => '1234567890', 'email' => 'maria@example.com', 'phone' => '123',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['identification', 'email', 'phone']);
        $response = $this->postJson(self::API.'/teachers', $payload + ['role' => 'administrador'])->assertCreated();
        $teacher = Usuario::query()->findOrFail($response->json('data.id'));
        $this->assertSame(['docente'], $teacher->roleSlugs()->all());
        $this->postJson(self::API.'/teachers', $payload)->assertUnprocessable()->assertJsonValidationErrors(['identification', 'email']);
    }

    public function test_teacher_deactivation_revokes_tokens_and_preserves_assignments(): void
    {
        $teacher = Usuario::factory()->withRole('docente')->create();
        $teacher->teachingCareers()->attach($this->career);
        $teacher->createToken('existing')->plainTextToken;
        $tutoring = $this->createTutoring();
        $tutoring->update(['fk_docente' => $teacher->getKey()]);
        Sanctum::actingAs($this->coordinator, ['*']);
        $this->patchJson(self::API.'/teachers/'.$teacher->getKey().'/deactivate')->assertOk()->assertJsonPath('data.is_active', false);
        $this->assertFalse($teacher->refresh()->estado);
        $this->assertSame(0, $teacher->tokens()->count());
        $this->assertSame($teacher->getKey(), $tutoring->refresh()->fk_docente);
        $this->getJson(self::API.'/teachers')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson(self::API.'/available-teachers')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_coordinator_cannot_modify_privileged_or_shared_teacher_accounts(): void
    {
        $shared = Usuario::factory()->withRole('docente')->create();
        $shared->teachingCareers()->attach([$this->career->getKey(), $this->otherCareer->getKey()]);
        $privileged = Usuario::factory()->withRole('docente')->create();
        $privileged->teachingCareers()->attach($this->career);
        $privileged->roles()->attach(Role::query()->where('slug', 'administrador')->value('id'));
        Sanctum::actingAs($this->coordinator, ['*']);
        $this->getJson(self::API.'/teachers')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.can_manage', false)->assertJsonPath('data.1.can_manage', false);
        foreach ([$shared, $privileged] as $teacher) {
            $this->patchJson(self::API.'/teachers/'.$teacher->getKey(), ['name' => 'Cambio indebido'])->assertForbidden();
            $this->patchJson(self::API.'/teachers/'.$teacher->getKey().'/deactivate')->assertForbidden();
        }
    }

    public function test_teacher_with_tutoring_in_another_career_cannot_be_modified_or_deactivated(): void
    {
        $teacher = Usuario::factory()->withRole('docente')->create();
        $teacher->teachingCareers()->attach($this->career);
        $foreignTutoring = $this->createTutoring($this->otherCycle);
        $foreignTutoring->update(['fk_docente' => $teacher->getKey()]);
        Sanctum::actingAs($this->coordinator, ['*']);
        $this->getJson(self::API.'/teachers')->assertOk()->assertJsonPath('data.0.can_manage', false);
        $this->patchJson(self::API.'/teachers/'.$teacher->getKey(), ['name' => 'Cambio indebido'])->assertForbidden();
        $this->patchJson(self::API.'/teachers/'.$teacher->getKey().'/deactivate')->assertForbidden();
        $this->assertTrue($teacher->refresh()->estado);
    }

    public function test_creation_rejects_missing_and_blank_required_fields(): void
    {
        Notification::fake();
        Sanctum::actingAs($this->coordinator, ['*']);
        $requiredFields = [
            'subjects' => ['career_id', 'name'],
            'teachers' => ['career_id', 'identification', 'name', 'email', 'phone'],
            'tutorings' => ['subject_id', 'cycle_id', 'period_id', 'modality_id'],
        ];
        foreach ($requiredFields as $resource => $fields) {
            $this->postJson(self::API.'/'.$resource, [])->assertUnprocessable()->assertJsonValidationErrors($fields);
            $this->postJson(self::API.'/'.$resource, array_fill_keys($fields, '   '))
                ->assertUnprocessable()->assertJsonValidationErrors($fields);
        }
        $this->assertDatabaseCount('subjects', 0);
        $this->assertDatabaseCount('asignatura_tutoria', 0);
        $this->assertDatabaseCount('career_teacher', 0);
        Notification::assertNothingSent();
    }

    public function test_coordinator_can_create_subject_with_cycle_and_parallel_or_new_parallel(): void
    {
        $parallel = Paralelo::query()->create(['nombre' => 'B', 'estado' => true]);
        Sanctum::actingAs($this->coordinator, ['*']);

        // 1. Create with existing parallel
        $response = $this->postJson(self::API.'/subjects', [
            'career_id' => $this->career->getKey(),
            'code' => 'CS-PAR-1',
            'name' => 'Programación I',
            'cycle_id' => $this->cycle->getKey(),
            'parallel_id' => $parallel->getKey(),
        ])->assertCreated();

        $subjectId = $response->json('data.id');
        $createdCycle = Ciclo::query()->where('fk_carrera', $this->career->getKey())
            ->where('numero', $this->cycle->numero)
            ->where('fk_paralelo', $parallel->getKey())
            ->firstOrFail();

        $this->assertDatabaseHas('subject_cycle', [
            'subject_id' => $subjectId,
            'cycle_id' => $createdCycle->getKey(),
        ]);

        // 2. Create with new parallel name
        $response2 = $this->postJson(self::API.'/subjects', [
            'career_id' => $this->career->getKey(),
            'code' => 'CS-PAR-2',
            'name' => 'Programación II',
            'cycle_id' => $this->cycle->getKey(),
            'new_parallel_name' => 'C',
        ])->assertCreated();

        $newParallel = Paralelo::query()->where('nombre', 'C')->firstOrFail();
        $createdCycle2 = Ciclo::query()->where('fk_carrera', $this->career->getKey())
            ->where('numero', $this->cycle->numero)
            ->where('fk_paralelo', $newParallel->getKey())
            ->firstOrFail();

        $this->assertDatabaseHas('subject_cycle', [
            'subject_id' => $response2->json('data.id'),
            'cycle_id' => $createdCycle2->getKey(),
        ]);
    }

    public function test_coordinator_can_assign_and_unassign_parallels_to_subject(): void
    {
        $parallelB = Paralelo::query()->create(['nombre' => 'B', 'estado' => true]);
        Sanctum::actingAs($this->coordinator, ['*']);

        $subject = $this->createSubject(code: 'CS-PAR-MGR');
        // Initially in cycle (which has parallel A)
        $this->assertDatabaseHas('subject_cycle', [
            'subject_id' => $subject->getKey(),
            'cycle_id' => $this->cycle->getKey(),
        ]);

        // 1. Assign parallel B
        $response = $this->postJson(self::API.'/subjects/'.$subject->getKey().'/parallels', [
            'parallel_id' => $parallelB->getKey(),
        ])->assertOk();

        $this->assertContains($parallelB->getKey(), $response->json('data.parallel_ids'));

        $cycleB = Ciclo::query()->where('fk_carrera', $this->career->getKey())
            ->where('numero', $this->cycle->numero)
            ->where('fk_paralelo', $parallelB->getKey())
            ->firstOrFail();

        $this->assertDatabaseHas('subject_cycle', [
            'subject_id' => $subject->getKey(),
            'cycle_id' => $cycleB->getKey(),
        ]);

        // 2. Unassign parallel B
        $this->deleteJson(self::API.'/subjects/'.$subject->getKey().'/parallels/'.$parallelB->getKey())
            ->assertOk();

        $this->assertDatabaseMissing('subject_cycle', [
            'subject_id' => $subject->getKey(),
            'cycle_id' => $cycleB->getKey(),
        ]);
    }

    public function test_coordinator_can_list_and_create_sections(): void
    {
        Sanctum::actingAs($this->coordinator, ['*']);
        $this->getJson(self::API.'/sections')->assertOk();

        $response = $this->postJson(self::API.'/sections', ['name' => 'D'])->assertCreated()
            ->assertJsonPath('data.name', 'D');

        $this->assertDatabaseHas('paralelo', ['id_paralelo' => $response->json('data.id'), 'nombre' => 'D']);
    }

    public function test_subject_code_is_optional_and_subject_can_have_multiple_parallels(): void
    {
        $parallel1 = Paralelo::query()->create(['nombre' => 'P1', 'estado' => true]);
        $parallel2 = Paralelo::query()->create(['nombre' => 'P2', 'estado' => true]);
        Sanctum::actingAs($this->coordinator, ['*']);

        // Subject without code and with multiple parallels
        $response = $this->postJson(self::API.'/subjects', [
            'career_id' => $this->career->getKey(),
            'name' => 'Materia Sin Código',
            'cycle_id' => $this->cycle->getKey(),
            'parallel_ids' => [$parallel1->getKey(), $parallel2->getKey()],
        ])->assertCreated()
            ->assertJsonPath('data.code', null)
            ->assertJsonPath('data.name', 'materia sin codigo');

        $subjectId = $response->json('data.id');
        $this->assertDatabaseHas('subjects', [
            'id' => $subjectId,
            'code' => null,
            'name' => 'materia sin codigo',
        ]);

        $this->assertSame(2, Ciclo::query()->where('fk_carrera', $this->career->getKey())
            ->where('numero', $this->cycle->numero)
            ->whereIn('fk_paralelo', [$parallel1->getKey(), $parallel2->getKey()])
            ->count());

        $this->assertSame(2, \DB::table('subject_cycle')->where('subject_id', $subjectId)->count());
    }
}

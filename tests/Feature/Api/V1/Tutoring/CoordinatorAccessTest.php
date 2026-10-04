<?php

namespace Tests\Feature\Api\V1\Tutoring;

use App\Models\Carrera;
use App\Models\Usuario;
use Laravel\Sanctum\Sanctum;

class CoordinatorAccessTest extends TutoringTestCase
{
    public function test_module_requires_authentication(): void
    {
        foreach (['subjects', 'teachers', 'available-teachers', 'tutorings', 'careers', 'cycles', 'periods', 'modalities'] as $resource) {
            $this->getJson(self::API.'/'.$resource)->assertUnauthorized();
        }
    }

    public function test_partial_two_factor_token_cannot_access_module_or_assign_careers(): void
    {
        Sanctum::actingAs($this->coordinator, ['two-factor:challenge']);
        $this->getJson(self::API.'/subjects')->assertForbidden();
        $this->postJson(self::API.'/subjects', [
            'career_id' => $this->career->getKey(), 'code' => 'CS-100', 'name' => 'Calidad',
        ])->assertForbidden();

        Sanctum::actingAs(Usuario::factory()->withRole('administrador')->create(), ['two-factor:challenge']);
        $this->putJson('/api/v1/admin/users/'.$this->coordinator->getKey().'/careers', [
            'career_ids' => [$this->otherCareer->getKey()],
        ])->assertForbidden();
    }

    public function test_student_teacher_and_degree_coordinator_cannot_use_the_module(): void
    {
        foreach (['estudiante', 'docente', 'coordinador_titulacion'] as $role) {
            Sanctum::actingAs(Usuario::factory()->withRole($role)->create(), ['*']);
            foreach (['subjects', 'teachers', 'available-teachers', 'tutorings', 'careers', 'cycles', 'periods', 'modalities'] as $resource) {
                $this->getJson(self::API.'/'.$resource)->assertForbidden();
            }
            $this->postJson(self::API.'/subjects', [])->assertForbidden();
            $this->postJson(self::API.'/teachers', [])->assertForbidden();
            $this->postJson(self::API.'/tutorings', [])->assertForbidden();
        }
    }

    public function test_inactive_and_unverified_coordinators_cannot_use_the_module(): void
    {
        foreach ([['estado' => false], ['email_verified_at' => null]] as $attributes) {
            $this->coordinator->update($attributes);
            Sanctum::actingAs($this->coordinator->refresh(), ['*']);
            $this->getJson(self::API.'/subjects')->assertForbidden();
            $this->getJson(self::API.'/available-teachers')->assertForbidden();
            $this->postJson(self::API.'/teachers', $this->teacherPayload())->assertForbidden();
            $this->coordinator->update(['estado' => true]);
        }
    }

    public function test_catalogs_only_include_active_options_for_assigned_careers(): void
    {
        Sanctum::actingAs($this->coordinator, ['*']);
        $this->getJson(self::API.'/careers')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->career->getKey());
        $this->getJson(self::API.'/cycles')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->cycle->getKey());
        $this->getJson(self::API.'/periods')->assertOk()->assertJsonPath('data.0.id', $this->period->getKey());
        $this->getJson(self::API.'/modalities')->assertOk()->assertJsonPath('data.0.id', $this->modality->getKey());

        $this->career->update(['estado' => false]);
        $this->period->update(['estado' => false]);
        $this->modality->update(['estado' => false]);
        foreach (['careers', 'cycles', 'periods', 'modalities'] as $resource) {
            $this->getJson(self::API.'/'.$resource)->assertOk()->assertJsonCount(0, 'data');
        }
    }

    public function test_coordinator_without_careers_sees_no_owned_records(): void
    {
        $this->createTutoring();
        $this->coordinator->coordinatedCareers()->detach();
        Sanctum::actingAs($this->coordinator, ['*']);
        foreach (['careers', 'cycles', 'subjects', 'teachers', 'tutorings'] as $resource) {
            $this->getJson(self::API.'/'.$resource)->assertOk()->assertJsonCount(0, 'data');
        }
    }

    public function test_only_administrator_can_assign_careers_and_can_revoke_all_of_them(): void
    {
        Sanctum::actingAs($this->coordinator, ['*']);
        $url = '/api/v1/admin/users/'.$this->coordinator->getKey().'/careers';
        $payload = ['career_ids' => [$this->otherCareer->getKey()]];
        $this->putJson($url, $payload)->assertForbidden();

        Sanctum::actingAs(Usuario::factory()->withRole('administrador')->create(), ['*']);
        $this->putJson($url, $payload)->assertOk()
            ->assertJsonPath('data.coordinated_career_ids', [$this->otherCareer->getKey()]);
        $this->assertDatabaseMissing('career_coordinator', [
            'user_id' => $this->coordinator->getKey(), 'career_id' => $this->career->getKey(),
        ]);
        $this->putJson($url, ['career_ids' => []])->assertOk()
            ->assertJsonPath('data.coordinated_career_ids', []);
        $this->assertDatabaseCount('career_coordinator', 0);
    }

    public function test_administrator_cannot_assign_invalid_careers_or_a_non_coordinator(): void
    {
        Sanctum::actingAs(Usuario::factory()->withRole('administrador')->create(), ['*']);
        $url = '/api/v1/admin/users/'.$this->coordinator->getKey().'/careers';
        $this->otherCareer->update(['estado' => false]);
        $this->putJson($url, ['career_ids' => [$this->otherCareer->getKey()]])->assertUnprocessable()
            ->assertJsonValidationErrors('career_ids.0');
        $this->putJson($url, ['career_ids' => [999999]])->assertUnprocessable();
        $this->putJson($url, ['career_ids' => [$this->career->getKey(), $this->career->getKey()]])->assertUnprocessable();
        $student = Usuario::factory()->withRole('estudiante')->create();
        $this->putJson('/api/v1/admin/users/'.$student->getKey().'/careers', [
            'career_ids' => [$this->career->getKey()],
        ])->assertUnprocessable();
        $this->assertDatabaseHas('career_coordinator', [
            'user_id' => $this->coordinator->getKey(), 'career_id' => $this->career->getKey(),
        ]);
    }

    public function test_administrator_can_read_and_manage_other_careers(): void
    {
        $subject = $this->createSubject($this->otherCycle);
        Sanctum::actingAs(Usuario::factory()->withRole('administrador')->create(), ['*']);
        $this->getJson(self::API.'/careers')->assertOk()->assertJsonCount(2, 'data');
        $this->patchJson(self::API.'/subjects/'.$subject->getKey(), ['name' => 'Álgebra'])
            ->assertOk()->assertJsonPath('data.name', 'algebra');
    }

    public function test_administrator_can_keep_an_existing_inactive_career_but_cannot_add_a_new_inactive_career(): void
    {
        $this->career->update(['estado' => false]);
        $newInactiveCareer = Carrera::query()->create([
            'fk_facultad' => $this->career->fk_facultad,
            'fk_modalidad' => $this->modality->getKey(),
            'nombre' => 'Carrera deshabilitada', 'estado' => false,
        ]);
        Sanctum::actingAs(Usuario::factory()->withRole('administrador')->create(), ['*']);
        $url = '/api/v1/admin/users/'.$this->coordinator->getKey().'/careers';
        $careerIds = [$this->career->getKey(), $this->otherCareer->getKey()];
        $this->putJson($url, ['career_ids' => $careerIds])->assertOk()
            ->assertJsonCount(2, 'data.coordinated_career_ids');
        $this->assertDatabaseHas('career_coordinator', [
            'user_id' => $this->coordinator->getKey(), 'career_id' => $this->career->getKey(),
        ]);
        $this->assertDatabaseHas('career_coordinator', [
            'user_id' => $this->coordinator->getKey(), 'career_id' => $this->otherCareer->getKey(),
        ]);
        $this->putJson($url, ['career_ids' => [...$careerIds, $newInactiveCareer->getKey()]])
            ->assertUnprocessable()->assertJsonValidationErrors('career_ids.2');
        $this->assertSame($careerIds, $this->coordinator->coordinatedCareers()->orderBy('carrera.id_carrera')->pluck('carrera.id_carrera')->all());
    }
}

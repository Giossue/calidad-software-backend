<?php

namespace Tests\Feature\Api\V1\Tutoring;

use App\Models\Carrera;
use App\Models\Facultad;
use App\Models\Malla;
use App\Models\Usuario;
use Laravel\Sanctum\Sanctum;

/**
 * Gestión de mallas de una carrera: el coordinador de la carrera crea, renombra,
 * marca vigente y elimina mallas; cada carrera tiene exactamente una vigente.
 */
class MallaManagementTest extends TutoringTestCase
{
    private function initial(?Carrera $career = null): Malla
    {
        return Malla::query()->where('career_id', ($career ?? $this->career)->getKey())->where('is_current', true)->firstOrFail();
    }

    public function test_a_new_career_starts_with_a_current_initial_malla(): void
    {
        $this->assertSame(['MALLA INICIAL'], Malla::query()->where('career_id', $this->career->getKey())->pluck('name')->all());
        $this->assertTrue($this->initial()->is_current);

        $admin = Usuario::factory()->withRole('administrador')->create();
        Sanctum::actingAs($admin, ['*']);
        $faculty = Facultad::query()->firstOrFail();
        $careerId = $this->postJson('/api/v1/admin/careers', [
            'faculty_id' => $faculty->getKey(), 'name' => 'Telemática', 'code' => 'TL-2026', 'modality_id' => $this->modality->getKey(),
        ])->assertCreated()->json('data.id');

        $this->assertSame([['name' => 'MALLA INICIAL', 'is_current' => true]],
            Malla::query()->where('career_id', $careerId)->get(['name', 'is_current'])->map(fn (Malla $malla): array => ['name' => $malla->name, 'is_current' => $malla->is_current])->all());
    }

    public function test_careers_list_their_mallas_current_first_then_by_name(): void
    {
        Malla::query()->create(['career_id' => $this->career->getKey(), 'name' => 'Malla 2020', 'is_current' => false]);
        Malla::query()->create(['career_id' => $this->career->getKey(), 'name' => 'Malla 2018', 'is_current' => false]);
        Sanctum::actingAs($this->coordinator, ['*']);

        $this->getJson(self::API.'/careers')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.mallas.*.name', ['MALLA INICIAL', 'MALLA 2018', 'MALLA 2020'])
            ->assertJsonPath('data.0.mallas.*.is_current', [true, false, false])
            ->assertJsonPath('data.0.mallas.0.id', $this->initial()->getKey());
    }

    public function test_coordinator_lists_the_mallas_of_a_career_with_their_counts(): void
    {
        $old = Malla::query()->create(['career_id' => $this->career->getKey(), 'name' => 'Malla 2020', 'is_current' => false]);
        $subject = $this->createSubject();
        $this->createTutoring(subject: $subject);
        Usuario::factory()->withRole('estudiante')->create(['fk_carrera' => $this->career->getKey()]);
        Usuario::factory()->withRole('docente')->create(['fk_carrera' => $this->career->getKey()]);
        Sanctum::actingAs($this->coordinator, ['*']);

        $this->getJson(self::API.'/mallas?career_id='.$this->career->getKey())->assertOk()
            ->assertJsonPath('data', [
                ['id' => $this->initial()->getKey(), 'career_id' => $this->career->getKey(), 'name' => 'MALLA INICIAL', 'is_current' => true, 'subjects_count' => 1, 'students_count' => 1, 'tutorings_count' => 1],
                ['id' => $old->getKey(), 'career_id' => $this->career->getKey(), 'name' => 'MALLA 2020', 'is_current' => false, 'subjects_count' => 0, 'students_count' => 0, 'tutorings_count' => 0],
            ]);
        $this->getJson(self::API.'/mallas?career_id='.$this->otherCareer->getKey())->assertForbidden();
    }

    public function test_coordinator_creates_a_malla_with_a_name_unique_in_the_career(): void
    {
        Sanctum::actingAs($this->coordinator, ['*']);

        $response = $this->postJson(self::API.'/mallas', ['career_id' => $this->career->getKey(), 'name' => '  Malla 2025 '])->assertCreated()
            ->assertJsonPath('data.name', 'MALLA 2025')
            ->assertJsonPath('data.career_id', $this->career->getKey())
            ->assertJsonPath('data.is_current', false)
            ->assertJsonPath('data.subjects_count', 0);
        $this->assertDatabaseHas('malla', ['id' => $response->json('data.id'), 'career_id' => $this->career->getKey(), 'name' => 'MALLA 2025', 'is_current' => false]);
        $this->assertTrue($this->initial()->is(Malla::query()->where('career_id', $this->career->getKey())->where('is_current', true)->first()));

        $this->postJson(self::API.'/mallas', ['career_id' => $this->career->getKey(), 'name' => 'Malla 2025'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name' => 'Ya existe una malla con ese nombre en la carrera.']);
        $this->postJson(self::API.'/mallas', ['career_id' => $this->career->getKey(), 'name' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson(self::API.'/mallas', ['career_id' => $this->career->getKey(), 'name' => str_repeat('a', 101)])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson(self::API.'/mallas', ['career_id' => $this->otherCareer->getKey(), 'name' => 'Malla 2025'])->assertForbidden();
        $this->assertSame(2, Malla::query()->where('career_id', $this->career->getKey())->count());

        // Otra carrera puede usar el mismo nombre.
        $this->otherCareer->coordinators()->attach($this->coordinator);
        $this->postJson(self::API.'/mallas', ['career_id' => $this->otherCareer->getKey(), 'name' => 'Malla 2025'])->assertCreated();
    }

    public function test_creating_a_current_malla_or_marking_one_current_unmarks_the_previous_one(): void
    {
        Sanctum::actingAs($this->coordinator, ['*']);
        $initial = $this->initial();

        $id = $this->postJson(self::API.'/mallas', ['career_id' => $this->career->getKey(), 'name' => 'Malla 2025', 'is_current' => true])
            ->assertCreated()->assertJsonPath('data.is_current', true)->json('data.id');
        $this->assertFalse($initial->refresh()->is_current);
        $this->assertSame([$id], Malla::query()->where('career_id', $this->career->getKey())->where('is_current', true)->pluck('id')->all());

        $this->patchJson(self::API.'/mallas/'.$initial->getKey(), ['is_current' => true])->assertOk()
            ->assertJsonPath('data.id', $initial->getKey())->assertJsonPath('data.is_current', true);
        $this->assertSame([$initial->getKey()], Malla::query()->where('career_id', $this->career->getKey())->where('is_current', true)->pluck('id')->all());
    }

    public function test_coordinator_renames_a_malla_and_cannot_unmark_the_current_one(): void
    {
        Sanctum::actingAs($this->coordinator, ['*']);
        $initial = $this->initial();
        $other = Malla::query()->create(['career_id' => $this->career->getKey(), 'name' => 'Malla 2020', 'is_current' => false]);

        $this->patchJson(self::API.'/mallas/'.$initial->getKey(), ['name' => 'Malla 2016'])->assertOk()
            ->assertJsonPath('data.name', 'MALLA 2016')->assertJsonPath('data.is_current', true);
        $this->assertDatabaseHas('malla', ['id' => $initial->getKey(), 'name' => 'MALLA 2016', 'is_current' => true]);

        $this->patchJson(self::API.'/mallas/'.$initial->getKey(), ['is_current' => false])->assertUnprocessable()->assertJsonValidationErrors('is_current');
        $this->patchJson(self::API.'/mallas/'.$other->getKey(), ['name' => 'Malla 2016'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name' => 'Ya existe una malla con ese nombre en la carrera.']);
        // Renombrarla con su propio nombre no choca consigo misma.
        $this->patchJson(self::API.'/mallas/'.$other->getKey(), ['name' => 'Malla 2020'])->assertOk();
        $this->assertTrue($initial->refresh()->is_current);
        $this->assertFalse($other->refresh()->is_current);

        $foreign = Malla::query()->where('career_id', $this->otherCareer->getKey())->firstOrFail();
        $this->patchJson(self::API.'/mallas/'.$foreign->getKey(), ['name' => 'Otra'])->assertForbidden();
        $this->assertSame('MALLA INICIAL', $foreign->refresh()->name);
    }

    public function test_a_malla_is_deleted_only_when_empty_and_not_current(): void
    {
        Sanctum::actingAs($this->coordinator, ['*']);
        $initial = $this->initial();
        $empty = Malla::query()->create(['career_id' => $this->career->getKey(), 'name' => 'Malla vacía', 'is_current' => false]);
        $withSubjects = Malla::query()->create(['career_id' => $this->career->getKey(), 'name' => 'Con asignaturas', 'is_current' => false]);
        $this->createSubject()->update(['malla_id' => $withSubjects->getKey()]);
        $withStudents = Malla::query()->create(['career_id' => $this->career->getKey(), 'name' => 'Con estudiantes', 'is_current' => false]);
        Usuario::factory()->withRole('estudiante')->create(['fk_carrera' => $this->career->getKey()])->forceFill(['malla_id' => $withStudents->getKey()])->save();

        $this->deleteJson(self::API.'/mallas/'.$initial->getKey())
            ->assertUnprocessable()->assertJsonValidationErrors(['malla' => 'No se puede eliminar la malla vigente.']);
        $this->deleteJson(self::API.'/mallas/'.$withSubjects->getKey())
            ->assertUnprocessable()->assertJsonValidationErrors(['malla' => 'La malla tiene asignaturas, estudiantes o tutorías.']);
        $this->deleteJson(self::API.'/mallas/'.$withStudents->getKey())
            ->assertUnprocessable()->assertJsonValidationErrors(['malla' => 'La malla tiene asignaturas, estudiantes o tutorías.']);
        $this->deleteJson(self::API.'/mallas/'.$empty->getKey())->assertNoContent();

        $this->assertDatabaseMissing('malla', ['id' => $empty->getKey()]);
        $this->assertSame(3, Malla::query()->where('career_id', $this->career->getKey())->count());

        $foreign = Malla::query()->create(['career_id' => $this->otherCareer->getKey(), 'name' => 'Ajena', 'is_current' => false]);
        $this->deleteJson(self::API.'/mallas/'.$foreign->getKey())->assertForbidden();
        $this->assertDatabaseHas('malla', ['id' => $foreign->getKey()]);
    }
}

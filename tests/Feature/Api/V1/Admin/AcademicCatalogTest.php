<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Carrera;
use App\Models\Ciclo;
use App\Models\Facultad;
use App\Models\Modalidad;
use App\Models\Paralelo;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_manage_a_career(): void
    {
        $this->actingAsAdministrator();
        $faculty = Facultad::query()->create([
            'nombre' => 'Facultad de Ingeniería',
            'estado' => true,
        ]);

        $response = $this->postJson('/api/v1/admin/careers', [
            'faculty_id' => $faculty->getKey(),
            'name' => 'Ingeniería de Software',
        ]);

        $careerId = $response->assertCreated()
            ->assertJsonPath('data.name', 'Ingeniería de Software')
            ->assertJsonPath('data.status', true)
            ->json('data.id');

        $career = Carrera::query()->findOrFail($careerId);

        $this->patchJson('/api/v1/admin/careers/'.$career->getKey(), [
            'name' => 'Ingeniería de Software Aplicado',
        ])->assertOk()->assertJsonPath('data.name', 'Ingeniería de Software Aplicado');

        $this->patchJson('/api/v1/admin/careers/'.$career->getKey().'/deactivate')
            ->assertOk()
            ->assertJsonPath('data.status', false);

        $this->assertFalse($career->fresh()->estado);

        $this->patchJson('/api/v1/admin/careers/'.$career->getKey().'/activate')
            ->assertOk()
            ->assertJsonPath('data.status', true);

        $this->assertTrue($career->fresh()->estado);
    }

    public function test_career_name_is_unique_inside_a_faculty(): void
    {
        $this->actingAsAdministrator();
        $faculty = Facultad::query()->create([
            'nombre' => 'Facultad de Ciencias',
            'estado' => true,
        ]);
        Carrera::query()->create([
            'fk_facultad' => $faculty->getKey(),
            'nombre' => 'Matemática',
            'estado' => true,
        ]);

        $this->postJson('/api/v1/admin/careers', [
            'faculty_id' => $faculty->getKey(),
            'name' => 'Matemática',
        ])->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_only_administrators_can_create_a_career(): void
    {
        $faculty = Facultad::query()->create([
            'nombre' => 'Facultad de Salud',
            'estado' => true,
        ]);
        $career = Carrera::query()->create([
            'fk_facultad' => $faculty->getKey(),
            'nombre' => 'Enfermería',
            'estado' => true,
        ]);

        $this->actingAs(Usuario::factory()->withRole('estudiante')->create(), 'sanctum');

        $this->postJson('/api/v1/admin/careers', [
            'faculty_id' => $faculty->getKey(),
            'name' => 'Enfermería',
        ])->assertForbidden();

        $this->patchJson('/api/v1/admin/careers/'.$career->getKey().'/deactivate')
            ->assertForbidden();
    }

    public function test_administrator_can_manage_a_cycle_and_only_rejects_exact_duplicates(): void
    {
        $this->actingAsAdministrator();
        $faculty = Facultad::query()->create([
            'nombre' => 'Facultad de Educación',
            'estado' => true,
        ]);
        $career = Carrera::query()->create([
            'fk_facultad' => $faculty->getKey(),
            'nombre' => 'Pedagogía',
            'estado' => true,
        ]);

        $response = $this->postJson('/api/v1/admin/cycles', [
            'career_id' => $career->getKey(),
            'name' => 'Primer ciclo',
            'number' => 1,
        ]);

        $cycleId = $response->assertCreated()
            ->assertJsonPath('data.number', 1)
            ->json('data.id');

        $cycle = Ciclo::query()->findOrFail($cycleId);

        // Mismo número, mismo paralelo (ninguno en este caso): rechazado, sería un duplicado exacto.
        $this->postJson('/api/v1/admin/cycles', [
            'career_id' => $career->getKey(),
            'name' => 'Primer ciclo',
            'number' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('number');

        $sectionA = Paralelo::query()->create(['nombre' => 'Paralelo A', 'estado' => true]);
        $sectionB = Paralelo::query()->create(['nombre' => 'Paralelo B', 'estado' => true]);

        // Mismo número, paralelo distinto: permitido (dos grupos del mismo ciclo).
        $this->postJson('/api/v1/admin/cycles', [
            'career_id' => $career->getKey(),
            'name' => 'Primer ciclo',
            'number' => 1,
            'paralelo_id' => $sectionA->getKey(),
        ])->assertCreated()
            ->assertJsonPath('data.number', 1)
            ->assertJsonPath('data.paralelo_name', 'Paralelo A');

        $this->postJson('/api/v1/admin/cycles', [
            'career_id' => $career->getKey(),
            'name' => 'Primer ciclo',
            'number' => 1,
            'paralelo_id' => $sectionB->getKey(),
        ])->assertCreated()->assertJsonPath('data.paralelo_name', 'Paralelo B');

        // Mismo número y mismo paralelo: rechazado.
        $this->postJson('/api/v1/admin/cycles', [
            'career_id' => $career->getKey(),
            'name' => 'Otro nombre',
            'number' => 1,
            'paralelo_id' => $sectionA->getKey(),
        ])->assertUnprocessable()->assertJsonValidationErrors('number');

        $this->patchJson('/api/v1/admin/cycles/'.$cycle->getKey(), [
            'name' => 'Ciclo inicial',
            'number' => 2,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Ciclo inicial')
            ->assertJsonPath('data.number', 2);

        $this->patchJson('/api/v1/admin/cycles/'.$cycle->getKey().'/deactivate')
            ->assertOk()
            ->assertJsonPath('data.status', false);

        $this->assertFalse($cycle->fresh()->estado);

        $this->patchJson('/api/v1/admin/cycles/'.$cycle->getKey().'/activate')
            ->assertOk()
            ->assertJsonPath('data.status', true);

        $this->assertTrue($cycle->fresh()->estado);
    }

    public function test_career_index_paginates_searches_and_reports_catalog_wide_counts(): void
    {
        $this->actingAsAdministrator();
        $engineering = Facultad::query()->create(['nombre' => 'Facultad de Ingeniería']);
        $health = Facultad::query()->create(['nombre' => 'Facultad de Salud']);
        Carrera::query()->create(['fk_facultad' => $engineering->getKey(), 'nombre' => 'Ingeniería de Software', 'estado' => true]);
        Carrera::query()->create(['fk_facultad' => $engineering->getKey(), 'nombre' => 'Ingeniería Civil', 'estado' => true]);
        Carrera::query()->create(['fk_facultad' => $health->getKey(), 'nombre' => 'Medicina', 'estado' => false]);

        $paginated = $this->getJson('/api/v1/admin/careers?per_page=2');
        $paginated->assertOk()
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.active_count', 2)
            ->assertJsonPath('meta.inactive_count', 1);
        $this->assertCount(2, $paginated->json('data'));

        $searched = $this->getJson('/api/v1/admin/careers?search=ingenier');
        $names = collect($searched->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Ingeniería de Software'));
        $this->assertTrue($names->contains('Ingeniería Civil'));
        $this->assertFalse($names->contains('Medicina'));

        $searchedByFaculty = $this->getJson('/api/v1/admin/careers?search=salud');
        $byFacultyNames = collect($searchedByFaculty->json('data'))->pluck('name');
        $this->assertTrue($byFacultyNames->contains('Medicina'));
    }

    public function test_career_all_mode_returns_every_active_career_unpaginated(): void
    {
        $this->actingAsAdministrator();
        $faculty = Facultad::query()->create(['nombre' => 'Facultad de Ciencias']);
        Carrera::query()->create(['fk_facultad' => $faculty->getKey(), 'nombre' => 'Física', 'estado' => true]);
        Carrera::query()->create(['fk_facultad' => $faculty->getKey(), 'nombre' => 'Química Inactiva', 'estado' => false]);

        $response = $this->getJson('/api/v1/admin/careers?all=1');

        $response->assertOk()->assertJsonMissingPath('meta');
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Física'));
        $this->assertFalse($names->contains('Química Inactiva'));
    }

    public function test_cycle_index_paginates_and_searches_by_own_or_career_name(): void
    {
        $this->actingAsAdministrator();
        $faculty = Facultad::query()->create(['nombre' => 'Facultad de Educación']);
        $career = Carrera::query()->create(['fk_facultad' => $faculty->getKey(), 'nombre' => 'Pedagogía Especial', 'estado' => true]);
        $otherCareer = Carrera::query()->create(['fk_facultad' => $faculty->getKey(), 'nombre' => 'Historia', 'estado' => true]);
        Ciclo::query()->create(['fk_carrera' => $career->getKey(), 'nombre' => 'Primer ciclo', 'numero' => 1, 'estado' => true]);
        Ciclo::query()->create(['fk_carrera' => $otherCareer->getKey(), 'nombre' => 'Nivel inicial', 'numero' => 1, 'estado' => false]);

        $paginated = $this->getJson('/api/v1/admin/cycles?per_page=1');
        $paginated->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.active_count', 1)
            ->assertJsonPath('meta.inactive_count', 1);

        $byCareerName = $this->getJson('/api/v1/admin/cycles?search=pedagog');
        $names = collect($byCareerName->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Primer ciclo'));
        $this->assertFalse($names->contains('Nivel inicial'));
    }

    public function test_cycle_index_filters_by_career_id_and_scopes_counts_to_it(): void
    {
        $this->actingAsAdministrator();
        $faculty = Facultad::query()->create(['nombre' => 'Facultad de Ciencias']);
        $career = Carrera::query()->create(['fk_facultad' => $faculty->getKey(), 'nombre' => 'Física', 'estado' => true]);
        $otherCareer = Carrera::query()->create(['fk_facultad' => $faculty->getKey(), 'nombre' => 'Química', 'estado' => true]);
        Ciclo::query()->create(['fk_carrera' => $career->getKey(), 'nombre' => 'Ciclo Uno', 'numero' => 1, 'estado' => true]);
        Ciclo::query()->create(['fk_carrera' => $career->getKey(), 'nombre' => 'Ciclo Dos', 'numero' => 2, 'estado' => false]);
        Ciclo::query()->create(['fk_carrera' => $otherCareer->getKey(), 'nombre' => 'Otro Ciclo', 'numero' => 1, 'estado' => true]);

        $response = $this->getJson("/api/v1/admin/cycles?career_id={$career->getKey()}");

        $response->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.active_count', 1)
            ->assertJsonPath('meta.inactive_count', 1);

        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Ciclo Uno'));
        $this->assertTrue($names->contains('Ciclo Dos'));
        $this->assertFalse($names->contains('Otro Ciclo'));
    }

    public function test_career_index_reports_cycle_counts(): void
    {
        $this->actingAsAdministrator();
        $faculty = Facultad::query()->create(['nombre' => 'Facultad de Derecho']);
        $career = Carrera::query()->create(['fk_facultad' => $faculty->getKey(), 'nombre' => 'Derecho', 'estado' => true]);
        Ciclo::query()->create(['fk_carrera' => $career->getKey(), 'nombre' => 'Ciclo Uno', 'numero' => 1, 'estado' => true]);
        Ciclo::query()->create(['fk_carrera' => $career->getKey(), 'nombre' => 'Ciclo Dos', 'numero' => 2, 'estado' => false]);

        $response = $this->getJson('/api/v1/admin/careers');

        $response->assertOk();
        $data = collect($response->json('data'))->firstWhere('id', $career->getKey());
        $this->assertSame(2, $data['cycles_count']);
        $this->assertSame(1, $data['active_cycles_count']);
    }

    public function test_career_can_be_created_with_a_modality_and_it_is_reported_in_the_index(): void
    {
        $this->actingAsAdministrator();
        $faculty = Facultad::query()->create(['nombre' => 'Facultad de Ingeniería', 'estado' => true]);
        $modality = Modalidad::query()->create(['nombre' => 'Presencial', 'estado' => true]);

        $response = $this->postJson('/api/v1/admin/careers', [
            'faculty_id' => $faculty->getKey(),
            'name' => 'Ingeniería en Sistemas',
            'modality_id' => $modality->getKey(),
        ]);

        $careerId = $response->assertCreated()
            ->assertJsonPath('data.modality_id', $modality->getKey())
            ->json('data.id');

        $this->assertDatabaseHas('carrera', [
            'id_carrera' => $careerId,
            'fk_modalidad' => $modality->getKey(),
        ]);

        $index = $this->getJson('/api/v1/admin/careers');
        $data = collect($index->json('data'))->firstWhere('id', $careerId);
        $this->assertSame($modality->getKey(), $data['modality_id']);
        $this->assertSame('Presencial', $data['modality_name']);
    }

    public function test_career_modality_remains_optional(): void
    {
        $this->actingAsAdministrator();
        $faculty = Facultad::query()->create(['nombre' => 'Facultad de Ciencias', 'estado' => true]);

        $response = $this->postJson('/api/v1/admin/careers', [
            'faculty_id' => $faculty->getKey(),
            'name' => 'Matemática Pura',
        ]);

        $careerId = $response->assertCreated()
            ->assertJsonPath('data.modality_id', null)
            ->json('data.id');

        $modality = Modalidad::query()->create(['nombre' => 'Virtual', 'estado' => true]);

        $this->patchJson('/api/v1/admin/careers/'.$careerId, ['modality_id' => $modality->getKey()])
            ->assertOk()
            ->assertJsonPath('data.modality_id', $modality->getKey());
    }

    public function test_career_rejects_an_inactive_or_unknown_modality(): void
    {
        $this->actingAsAdministrator();
        $faculty = Facultad::query()->create(['nombre' => 'Facultad de Artes', 'estado' => true]);
        $inactiveModality = Modalidad::query()->create(['nombre' => 'Modalidad Inactiva', 'estado' => false]);

        $this->postJson('/api/v1/admin/careers', [
            'faculty_id' => $faculty->getKey(),
            'name' => 'Bellas Artes',
            'modality_id' => $inactiveModality->getKey(),
        ])->assertUnprocessable()->assertJsonValidationErrors('modality_id');

        $this->postJson('/api/v1/admin/careers', [
            'faculty_id' => $faculty->getKey(),
            'name' => 'Bellas Artes',
            'modality_id' => 99999,
        ])->assertUnprocessable()->assertJsonValidationErrors('modality_id');
    }

    public function test_administrator_can_manage_sections(): void
    {
        $this->actingAsAdministrator();

        $response = $this->postJson('/api/v1/admin/sections', ['name' => 'Paralelo A']);
        $sectionId = $response->assertCreated()
            ->assertJsonPath('data.name', 'Paralelo A')
            ->assertJsonPath('data.is_active', true)
            ->json('data.id');

        $this->postJson('/api/v1/admin/sections', ['name' => 'Paralelo A'])
            ->assertUnprocessable()->assertJsonValidationErrors('nombre');

        $this->patchJson('/api/v1/admin/sections/'.$sectionId, ['name' => 'Paralelo A1'])
            ->assertOk()->assertJsonPath('data.name', 'Paralelo A1');

        $this->patchJson('/api/v1/admin/sections/'.$sectionId.'/deactivate')
            ->assertOk()->assertJsonPath('data.is_active', false);

        $this->patchJson('/api/v1/admin/sections/'.$sectionId.'/activate')
            ->assertOk()->assertJsonPath('data.is_active', true);

        $this->getJson('/api/v1/admin/sections')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Paralelo A1');
    }

    public function test_only_administrators_can_manage_sections(): void
    {
        $this->actingAs(Usuario::factory()->withRole('estudiante')->create(), 'sanctum');

        $this->postJson('/api/v1/admin/sections', ['name' => 'Paralelo A'])->assertForbidden();
        $this->getJson('/api/v1/admin/sections')->assertForbidden();
    }

    private function actingAsAdministrator(): Usuario
    {
        $administrator = Usuario::factory()->withRole('administrador')->create();
        $this->actingAs($administrator, 'sanctum');

        return $administrator;
    }
}

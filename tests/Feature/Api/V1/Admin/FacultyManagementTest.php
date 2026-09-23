<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Carrera;
use App\Models\Facultad;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FacultyManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(Usuario::factory()->create(['rol' => 'administrador']));
    }

    public function test_admin_can_register_a_faculty(): void
    {
        $response = $this->postJson('/api/v1/faculties', [
            'name' => 'Facultad de Ingeniería',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Facultad de Ingeniería')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('facultad', [
            'nombre' => 'Facultad de Ingeniería',
            'estado' => true,
        ]);
    }

    public function test_registering_a_faculty_rejects_duplicate_names(): void
    {
        Facultad::create(['nombre' => 'Facultad de Ingeniería']);

        $this->postJson('/api/v1/faculties', ['name' => 'Facultad de Ingeniería'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_admin_can_update_a_faculty(): void
    {
        $faculty = Facultad::create(['nombre' => 'Ingeniería']);

        $response = $this->patchJson("/api/v1/faculties/{$faculty->getKey()}", [
            'name' => 'Facultad de Ingeniería',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Facultad de Ingeniería')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('facultad', [
            'id_facultad' => $faculty->getKey(),
            'nombre' => 'Facultad de Ingeniería',
        ]);
    }

    public function test_updating_a_faculty_rejects_a_name_used_by_another_record(): void
    {
        Facultad::create(['nombre' => 'Facultad de Salud']);
        $faculty = Facultad::create(['nombre' => 'Facultad de Educación']);

        $this->patchJson("/api/v1/faculties/{$faculty->getKey()}", [
            'name' => 'Facultad de Salud',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_admin_can_deactivate_a_faculty_without_deleting_the_record(): void
    {
        $faculty = Facultad::create(['nombre' => 'Facultad de Derecho']);

        $response = $this->patchJson("/api/v1/faculties/{$faculty->getKey()}/deactivate");

        $response->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('facultad', [
            'id_facultad' => $faculty->getKey(),
            'nombre' => 'Facultad de Derecho',
            'estado' => false,
        ]);
        $this->assertDatabaseCount('facultad', 1);
    }

    public function test_faculty_endpoints_return_404_for_unknown_records(): void
    {
        $this->patchJson('/api/v1/faculties/99999', ['name' => 'Inexistente'])
            ->assertNotFound();
        $this->patchJson('/api/v1/faculties/99999/deactivate')
            ->assertNotFound();
    }

    public function test_admin_can_reactivate_a_deactivated_faculty(): void
    {
        $faculty = Facultad::create(['nombre' => 'Facultad de Arquitectura', 'estado' => false]);

        $response = $this->patchJson("/api/v1/faculties/{$faculty->getKey()}/activate");

        $response->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('facultad', [
            'id_facultad' => $faculty->getKey(),
            'estado' => true,
        ]);
    }

    public function test_faculty_index_lists_both_active_and_inactive_faculties(): void
    {
        $active = Facultad::create(['nombre' => 'Facultad Activa']);
        $inactive = Facultad::create(['nombre' => 'Facultad Inactiva', 'estado' => false]);

        $response = $this->getJson('/api/v1/admin/faculties');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($active->getKey()));
        $this->assertTrue($ids->contains($inactive->getKey()));
    }

    public function test_faculty_cannot_be_deactivated_while_it_has_an_active_career(): void
    {
        $faculty = Facultad::create(['nombre' => 'Facultad de Ciencias']);
        Carrera::create(['fk_facultad' => $faculty->getKey(), 'nombre' => 'Matemática', 'estado' => true]);

        $this->patchJson("/api/v1/faculties/{$faculty->getKey()}/deactivate")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['faculty']);

        $this->assertTrue($faculty->fresh()->estado);
    }

    public function test_faculty_can_be_deactivated_once_its_careers_are_inactive(): void
    {
        $faculty = Facultad::create(['nombre' => 'Facultad de Artes']);
        Carrera::create(['fk_facultad' => $faculty->getKey(), 'nombre' => 'Pintura', 'estado' => false]);

        $this->patchJson("/api/v1/faculties/{$faculty->getKey()}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertFalse($faculty->fresh()->estado);
    }

    public function test_faculty_can_be_deactivated_when_it_has_no_careers(): void
    {
        $faculty = Facultad::create(['nombre' => 'Facultad Sin Carreras']);

        $this->patchJson("/api/v1/faculties/{$faculty->getKey()}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_faculty_index_reports_career_counts(): void
    {
        $faculty = Facultad::create(['nombre' => 'Facultad de Derecho']);
        Carrera::create(['fk_facultad' => $faculty->getKey(), 'nombre' => 'Derecho Penal', 'estado' => true]);
        Carrera::create(['fk_facultad' => $faculty->getKey(), 'nombre' => 'Derecho Civil', 'estado' => false]);

        $response = $this->getJson('/api/v1/admin/faculties');

        $response->assertOk();
        $data = collect($response->json('data'))->firstWhere('id', $faculty->getKey());
        $this->assertSame(2, $data['careers_count']);
        $this->assertSame(1, $data['active_careers_count']);
    }

    public function test_faculty_index_paginates_and_reports_catalog_wide_counts(): void
    {
        Facultad::create(['nombre' => 'Facultad A']);
        Facultad::create(['nombre' => 'Facultad B']);
        Facultad::create(['nombre' => 'Facultad C', 'estado' => false]);

        $response = $this->getJson('/api/v1/admin/faculties?per_page=2');

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.active_count', 2)
            ->assertJsonPath('meta.inactive_count', 1);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_faculty_index_filters_by_search(): void
    {
        Facultad::create(['nombre' => 'Facultad de Ingeniería']);
        Facultad::create(['nombre' => 'Facultad de Salud']);

        $response = $this->getJson('/api/v1/admin/faculties?search=ingenier');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Facultad de Ingeniería'));
        $this->assertFalse($names->contains('Facultad de Salud'));
    }

    public function test_faculty_all_mode_returns_every_active_faculty_unpaginated(): void
    {
        Facultad::create(['nombre' => 'Facultad Activa Uno']);
        Facultad::create(['nombre' => 'Facultad Activa Dos']);
        Facultad::create(['nombre' => 'Facultad Inactiva', 'estado' => false]);

        $response = $this->getJson('/api/v1/admin/faculties?all=1');

        $response->assertOk()->assertJsonMissingPath('meta');
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Facultad Activa Uno'));
        $this->assertTrue($names->contains('Facultad Activa Dos'));
        $this->assertFalse($names->contains('Facultad Inactiva'));
    }
}

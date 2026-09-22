<?php

namespace Tests\Feature\Api\V1\Admin;

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
}

<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Carrera;
use App\Models\Ciclo;
use App\Models\Facultad;
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

        $this->actingAs(Usuario::factory()->create(['rol' => 'estudiante']), 'sanctum');

        $this->postJson('/api/v1/admin/careers', [
            'faculty_id' => $faculty->getKey(),
            'name' => 'Enfermería',
        ])->assertForbidden();

        $this->patchJson('/api/v1/admin/careers/'.$career->getKey().'/deactivate')
            ->assertForbidden();
    }

    public function test_administrator_can_manage_a_cycle_and_cycle_numbers_are_unique(): void
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

        $this->postJson('/api/v1/admin/cycles', [
            'career_id' => $career->getKey(),
            'name' => 'Otro nombre',
            'number' => 1,
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

    private function actingAsAdministrator(): Usuario
    {
        $administrator = Usuario::factory()->create(['rol' => 'administrador']);
        $this->actingAs($administrator, 'sanctum');

        return $administrator;
    }
}

<?php

namespace Tests\Feature\Api\V1\Coordination;

use App\Models\Paralelo;
use App\Models\PeriodoAcademico;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DegreeCoordinatorSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_retrieve_current_academic_period(): void
    {
        $activePeriod = PeriodoAcademico::query()->create([
            'nombre' => 'PAO 2026-1',
            'fecha_inicio' => '2026-05-01',
            'fecha_fin' => '2026-09-30',
            'estado' => true,
        ]);

        $coordinator = Usuario::factory()->withRole('coordinador_titulacion')->create();
        Sanctum::actingAs($coordinator);

        $response = $this->getJson('/api/v1/academic-periods/current');

        $response->assertOk()
            ->assertJsonPath('data.id', $activePeriod->getKey())
            ->assertJsonPath('data.name', 'PAO 2026-1')
            ->assertJsonPath('data.is_active', true);
    }

    public function test_current_academic_period_returns_404_when_no_active_period_exists(): void
    {
        PeriodoAcademico::query()->create([
            'nombre' => 'PAO 2025-2 (Finalizado)',
            'fecha_inicio' => '2025-10-01',
            'fecha_fin' => '2026-02-28',
            'estado' => false,
        ]);

        $coordinator = Usuario::factory()->withRole('coordinador_titulacion')->create();
        Sanctum::actingAs($coordinator);

        $response = $this->getJson('/api/v1/academic-periods/current');

        $response->assertNotFound()
            ->assertJsonPath('message', 'No existe un período académico vigente actualmente.');
    }

    public function test_coordinador_titulacion_can_register_a_section_to_the_current_academic_period(): void
    {
        $activePeriod = PeriodoAcademico::query()->create([
            'nombre' => 'PAO 2026-1',
            'fecha_inicio' => '2026-05-01',
            'fecha_fin' => '2026-09-30',
            'estado' => true,
        ]);

        $coordinator = Usuario::factory()->withRole('coordinador_titulacion')->create();
        Sanctum::actingAs($coordinator);

        $response = $this->postJson('/api/v1/academic-periods/current/sections', [
            'name' => 'A',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'A')
            ->assertJsonPath('data.academic_period.id', $activePeriod->getKey())
            ->assertJsonPath('data.academic_period.name', 'PAO 2026-1');

        $this->assertDatabaseHas('paralelo', [
            'nombre' => 'A',
            'estado' => true,
        ]);

        $section = Paralelo::query()->where('nombre', 'A')->firstOrFail();

        $this->assertDatabaseHas('periodo_paralelo', [
            'fk_periodo' => $activePeriod->getKey(),
            'fk_paralelo' => $section->getKey(),
        ]);

        // Consulta de paralelos registrados para el período vigente
        $listResponse = $this->getJson('/api/v1/academic-periods/current/sections');
        $listResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'A');
    }

    public function test_registering_a_section_fails_when_no_active_academic_period_exists(): void
    {
        $coordinator = Usuario::factory()->withRole('coordinador_titulacion')->create();
        Sanctum::actingAs($coordinator);

        $response = $this->postJson('/api/v1/academic-periods/current/sections', [
            'name' => 'B',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'No es posible registrar un paralelo porque no existe un período académico vigente.');
    }

    public function test_unauthorized_user_cannot_register_a_section_to_academic_period(): void
    {
        PeriodoAcademico::query()->create([
            'nombre' => 'PAO 2026-1',
            'fecha_inicio' => '2026-05-01',
            'fecha_fin' => '2026-09-30',
            'estado' => true,
        ]);

        $student = Usuario::factory()->withRole('estudiante')->create();
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/v1/academic-periods/current/sections', [
            'name' => 'C',
        ]);

        $response->assertForbidden();
    }
}

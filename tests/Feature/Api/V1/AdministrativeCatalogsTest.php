<?php

namespace Tests\Feature\Api\V1;

use App\Models\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdministrativeCatalogsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('usuario', function (Blueprint $table): void {
            $table->increments('id_usuario');
            $table->string('cedula', 20)->unique();
            $table->string('nombre', 150);
            $table->string('correo', 150)->unique();
            $table->string('password_hash');
            $table->string('rol', 40);
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });

        Schema::create('periodo_academico', function (Blueprint $table): void {
            $table->increments('id_periodo');
            $table->string('nombre', 100)->unique();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });

        Schema::create('modalidad', function (Blueprint $table): void {
            $table->increments('id_modalidad');
            $table->string('nombre', 100)->unique();
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });
    }

    public function test_administrator_can_create_update_and_deactivate_an_academic_period(): void
    {
        Sanctum::actingAs($this->administrator());

        $created = $this->postJson('/api/v1/academic-periods', [
            'nombre' => '  2026-A  ',
            'fecha_inicio' => '2026-04-01',
            'fecha_fin' => '2026-08-31',
        ])->assertCreated()->assertJsonPath('data.name', '2026-A');

        $id = $created->json('data.id');

        $this->patchJson("/api/v1/academic-periods/{$id}", [
            'nombre' => '2026-B',
            'fecha_inicio' => '2026-09-01',
            'fecha_fin' => '2027-02-28',
        ])->assertOk()->assertJsonPath('data.name', '2026-B');

        $this->patchJson("/api/v1/academic-periods/{$id}/deactivate")
            ->assertOk()->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('periodo_academico', ['id_periodo' => $id, 'estado' => false]);

        $this->patchJson("/api/v1/academic-periods/{$id}/activate")
            ->assertOk()->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('periodo_academico', ['id_periodo' => $id, 'estado' => true]);
    }

    public function test_academic_period_rejects_an_end_date_before_its_start_date(): void
    {
        Sanctum::actingAs($this->administrator());

        $this->postJson('/api/v1/academic-periods', [
            'nombre' => '2026-A',
            'fecha_inicio' => '2026-08-31',
            'fecha_fin' => '2026-04-01',
        ])->assertUnprocessable()->assertJsonValidationErrors('fecha_fin');
    }

    public function test_administrator_can_create_update_and_deactivate_a_modality(): void
    {
        Sanctum::actingAs($this->administrator());

        $created = $this->postJson('/api/v1/modalities', ['nombre' => '  Virtual  '])
            ->assertCreated()->assertJsonPath('data.name', 'Virtual');

        $id = $created->json('data.id');

        $this->patchJson("/api/v1/modalities/{$id}", ['nombre' => 'Híbrida'])
            ->assertOk()->assertJsonPath('data.name', 'Híbrida');

        $this->patchJson("/api/v1/modalities/{$id}/deactivate")
            ->assertOk()->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('modalidad', ['id_modalidad' => $id, 'estado' => false]);

        $this->patchJson("/api/v1/modalities/{$id}/activate")
            ->assertOk()->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('modalidad', ['id_modalidad' => $id, 'estado' => true]);
    }

    public function test_non_administrator_cannot_manage_administrative_catalogs(): void
    {
        Sanctum::actingAs($this->user('docente'));

        $this->postJson('/api/v1/modalities', ['nombre' => 'Virtual'])->assertForbidden();
        $this->getJson('/api/v1/academic-periods')->assertForbidden();
    }

    private function administrator(): Usuario
    {
        return $this->user('administrador');
    }

    private function user(string $role): Usuario
    {
        return Usuario::query()->create([
            'cedula' => fake()->unique()->numerify('##########'),
            'nombre' => 'Usuario de prueba',
            'correo' => fake()->unique()->safeEmail(),
            'password_hash' => 'password',
            'rol' => $role,
        ]);
    }
}

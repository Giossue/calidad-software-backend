<?php

namespace Tests\Feature\Api\V1;

use App\Models\Role;
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
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('name', 100);
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table): void {
            $table->unsignedInteger('user_id');
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamp('assigned_at')->useCurrent();

            $table->foreign('user_id')->references('id_usuario')->on('usuario')->cascadeOnDelete();
            $table->primary(['user_id', 'role_id']);
        });

        foreach (['estudiante', 'docente', 'coordinador_carrera', 'coordinador_titulacion', 'administrador'] as $slug) {
            Role::query()->create(['slug' => $slug, 'name' => $slug]);
        }

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

    public function test_academic_period_index_paginates_searches_and_reports_catalog_wide_counts(): void
    {
        Sanctum::actingAs($this->administrator());

        $this->postJson('/api/v1/academic-periods', ['nombre' => '2026-A', 'fecha_inicio' => '2026-04-01', 'fecha_fin' => '2026-08-31'])->assertCreated();
        $this->postJson('/api/v1/academic-periods', ['nombre' => '2026-B', 'fecha_inicio' => '2026-09-01', 'fecha_fin' => '2027-02-28'])->assertCreated();
        $inactiveId = $this->postJson('/api/v1/academic-periods', ['nombre' => '2025-B', 'fecha_inicio' => '2025-09-01', 'fecha_fin' => '2026-02-28'])
            ->assertCreated()->json('data.id');
        $this->patchJson("/api/v1/academic-periods/{$inactiveId}/deactivate")->assertOk();

        $paginated = $this->getJson('/api/v1/academic-periods?per_page=2');
        $paginated->assertOk()
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.active_count', 1)
            ->assertJsonPath('meta.inactive_count', 2);

        $searched = $this->getJson('/api/v1/academic-periods?search=2026-A');
        $names = collect($searched->json('data'))->pluck('name');
        $this->assertTrue($names->contains('2026-A'));
        $this->assertFalse($names->contains('2026-B'));
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

    public function test_only_one_academic_period_can_be_active_at_the_same_time(): void
    {
        Sanctum::actingAs($this->administrator());

        // Período 1 vigente
        $period1 = $this->postJson('/api/v1/academic-periods', [
            'nombre' => 'PAO I 2026',
            'fecha_inicio' => '2026-05-01',
            'fecha_fin' => '2026-10-31',
        ])->assertCreated();

        $this->assertTrue($period1->json('data.is_active'));

        // Período 2 consecutivo en el futuro: se registra inactivo porque aún no llega su fecha
        $period2 = $this->postJson('/api/v1/academic-periods', [
            'nombre' => 'PAO II 2026',
            'fecha_inicio' => '2026-11-01',
            'fecha_fin' => '2027-03-31',
        ])->assertCreated();

        $this->assertFalse($period2->json('data.is_active'));

        // Al consultar el catálogo, solo 1 está activo
        $response = $this->getJson('/api/v1/academic-periods');
        $response->assertOk()
            ->assertJsonPath('meta.active_count', 1)
            ->assertJsonPath('meta.inactive_count', 1);
    }

    public function test_academic_period_rejects_overlapping_dates_with_another_period(): void
    {
        Sanctum::actingAs($this->administrator());

        $this->postJson('/api/v1/academic-periods', [
            'nombre' => 'PAO I 2026',
            'fecha_inicio' => '2026-05-01',
            'fecha_fin' => '2026-10-31',
        ])->assertCreated();

        // Intento de crear período solapado con el existente
        $response = $this->postJson('/api/v1/academic-periods', [
            'nombre' => 'PAO Solapado',
            'fecha_inicio' => '2026-08-01',
            'fecha_fin' => '2026-12-31',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('fecha_inicio');
    }

    public function test_activating_an_expired_academic_period_is_rejected(): void
    {
        Sanctum::actingAs($this->administrator());

        // Período ya finalizado (cerrado en su fecha de fin)
        $expired = $this->postJson('/api/v1/academic-periods', [
            'nombre' => 'PAO Pasado 2025',
            'fecha_inicio' => '2025-05-01',
            'fecha_fin' => '2025-09-30',
        ])->assertCreated();

        $this->assertFalse($expired->json('data.is_active'));

        // Intentar habilitar un período cuya fecha ya expiró debe fallar
        $this->patchJson("/api/v1/academic-periods/{$expired->json('data.id')}/activate")
            ->assertStatus(422);
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
        $user = Usuario::query()->create([
            'cedula' => fake()->unique()->numerify('##########'),
            'nombre' => 'Usuario de prueba',
            'correo' => fake()->unique()->safeEmail(),
            'password_hash' => 'password',
        ]);

        $user->roles()->attach(Role::query()->where('slug', $role)->value('id'));

        return $user;
    }
}

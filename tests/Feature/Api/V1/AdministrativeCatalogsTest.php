<?php

namespace Tests\Feature\Api\V1;

use App\Models\PeriodoAcademico;
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

        Schema::create('asignatura_tutoria', function (Blueprint $table): void {
            $table->increments('id_asig_tutoria');
            $table->unsignedInteger('fk_periodo');
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

    public function test_administrator_can_create_and_update_an_academic_period(): void
    {
        $this->travelTo('2026-05-01 12:00:00');
        Sanctum::actingAs($this->administrator());

        $created = $this->postJson('/api/v1/academic-periods', [
            'nombre' => '  2026-A  ',
            'fecha_inicio' => '2026-04-01',
            'fecha_fin' => '2026-08-31',
        ])->assertCreated()
            ->assertJsonPath('data.name', '2026-A')
            ->assertJsonPath('data.is_active', true);

        $id = $created->json('data.id');

        $this->patchJson("/api/v1/academic-periods/{$id}", [
            'nombre' => '2026-B',
            'fecha_inicio' => '2026-04-01',
            'fecha_fin' => '2026-09-30',
        ])->assertOk()->assertJsonPath('data.name', '2026-B');
    }

    public function test_academic_period_cannot_be_deactivated_manually(): void
    {
        $this->travelTo('2026-05-01 12:00:00');
        Sanctum::actingAs($this->administrator());

        $id = $this->postJson('/api/v1/academic-periods', ['nombre' => '2026-A', 'fecha_inicio' => '2026-04-01', 'fecha_fin' => '2026-08-31'])
            ->assertCreated()->json('data.id');

        $this->patchJson("/api/v1/academic-periods/{$id}/deactivate")->assertNotFound();
        $this->patchJson("/api/v1/admin/academic-periods/{$id}/deactivate")->assertNotFound();

        $this->patchJson("/api/v1/academic-periods/{$id}", [
            'nombre' => '2026-A',
            'fecha_inicio' => '2026-04-01',
            'fecha_fin' => '2026-04-30',
        ])->assertUnprocessable()->assertJsonValidationErrors('fecha_fin');

        $this->assertDatabaseHas('periodo_academico', ['id_periodo' => $id, 'estado' => true]);
    }

    public function test_only_one_academic_period_can_be_active(): void
    {
        $this->travelTo('2026-05-01 12:00:00');
        Sanctum::actingAs($this->administrator());

        $this->postJson('/api/v1/academic-periods', ['nombre' => '2026-A', 'fecha_inicio' => '2026-04-01', 'fecha_fin' => '2026-08-31'])
            ->assertCreated()->assertJsonPath('data.is_active', true);

        $nextId = $this->postJson('/api/v1/academic-periods', ['nombre' => '2026-B', 'fecha_inicio' => '2026-09-01', 'fecha_fin' => '2027-02-28'])
            ->assertCreated()->assertJsonPath('data.is_active', false)->json('data.id');

        $this->patchJson("/api/v1/academic-periods/{$nextId}/activate")
            ->assertUnprocessable()->assertJsonValidationErrors('period');

        $this->assertSame(1, PeriodoAcademico::query()->where('estado', true)->count());
    }

    public function test_academic_period_expires_after_its_end_date_in_ecuador_time(): void
    {
        $this->travelTo('2026-05-01 12:00:00');
        Sanctum::actingAs($this->administrator());

        $currentId = $this->postJson('/api/v1/academic-periods', ['nombre' => '2026-A', 'fecha_inicio' => '2026-04-01', 'fecha_fin' => '2026-08-31'])
            ->assertCreated()->json('data.id');
        $nextId = $this->postJson('/api/v1/academic-periods', ['nombre' => '2026-B', 'fecha_inicio' => '2026-09-01', 'fecha_fin' => '2027-02-28'])
            ->assertCreated()->json('data.id');

        // 2026-09-01 03:00 UTC sigue siendo 31/08 en Ecuador.
        $this->travelTo('2026-09-01 03:00:00');
        $this->getJson('/api/v1/academic-periods')->assertOk()->assertJsonPath('meta.active_count', 1);
        $this->assertDatabaseHas('periodo_academico', ['id_periodo' => $currentId, 'estado' => true]);

        $this->travelTo('2026-09-01 06:00:00');
        $this->getJson('/api/v1/academic-periods')->assertOk()->assertJsonPath('meta.active_count', 0);
        $this->assertDatabaseHas('periodo_academico', ['id_periodo' => $currentId, 'estado' => false]);

        $this->patchJson("/api/v1/academic-periods/{$currentId}/activate")
            ->assertUnprocessable()->assertJsonValidationErrors('period');
        $this->patchJson("/api/v1/academic-periods/{$nextId}/activate")
            ->assertOk()->assertJsonPath('data.is_active', true);
    }

    public function test_academic_period_index_paginates_searches_and_reports_catalog_wide_counts(): void
    {
        $this->travelTo('2026-05-01 12:00:00');
        Sanctum::actingAs($this->administrator());

        $this->postJson('/api/v1/academic-periods', ['nombre' => '2026-A', 'fecha_inicio' => '2026-04-01', 'fecha_fin' => '2026-08-31'])->assertCreated();
        $this->postJson('/api/v1/academic-periods', ['nombre' => '2026-B', 'fecha_inicio' => '2026-09-01', 'fecha_fin' => '2027-02-28'])->assertCreated();
        $this->postJson('/api/v1/academic-periods', ['nombre' => '2025-B', 'fecha_inicio' => '2025-09-01', 'fecha_fin' => '2026-02-28'])
            ->assertCreated()->assertJsonPath('data.is_active', false);

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

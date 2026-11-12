<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Carrera;
use App\Models\Ciclo;
use App\Models\Facultad;
use App\Models\Modalidad;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
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
            'code' => 'SW-2020',
        ]);

        $careerId = $response->assertCreated()
            ->assertJsonPath('data.name', 'Ingeniería de Software')
            ->assertJsonPath('data.code', 'SW-2020')
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

    public function test_creating_a_career_generates_eight_cycles_without_parallel(): void
    {
        $this->actingAsAdministrator();
        $faculty = Facultad::query()->create(['nombre' => 'Facultad de Ingeniería', 'estado' => true]);

        $careerId = $this->postJson('/api/v1/admin/careers', [
            'faculty_id' => $faculty->getKey(),
            'name' => 'Ingeniería de Software',
            'code' => 'SW-2020',
        ])->assertCreated()->json('data.id');

        $cycles = Ciclo::query()->where('fk_carrera', $careerId)->orderBy('numero')->get();

        $this->assertSame(range(1, 8), $cycles->pluck('numero')->all());
        $this->assertCount(8, $cycles->whereNull('fk_paralelo'));
        $this->assertCount(8, $cycles->where('estado', true));
        $this->assertSame('Primer ciclo', $cycles->first()->nombre);
        $this->assertSame('Octavo ciclo', $cycles->last()->nombre);
    }

    public function test_career_can_be_created_with_a_custom_number_of_cycles(): void
    {
        $this->actingAsAdministrator();
        $faculty = Facultad::query()->create(['nombre' => 'Facultad de Ingeniería', 'estado' => true]);

        $careerId = $this->postJson('/api/v1/admin/careers', [
            'faculty_id' => $faculty->getKey(),
            'name' => 'Tecnología Superior',
            'code' => 'TS-01',
            'cycles_count' => 4,
        ])->assertCreated()->json('data.id');

        $this->assertSame(4, Ciclo::query()->where('fk_carrera', $careerId)->count());

        $this->postJson('/api/v1/admin/careers', [
            'faculty_id' => $faculty->getKey(),
            'name' => 'Sin ciclos',
            'code' => 'SC-01',
            'cycles_count' => 13,
        ])->assertUnprocessable()->assertJsonValidationErrors('cycles_count');
    }

    public function test_editing_a_career_can_add_cycles_but_never_remove_them(): void
    {
        $this->actingAsAdministrator();
        $faculty = Facultad::query()->create(['nombre' => 'Facultad de Ingeniería', 'estado' => true]);
        $careerId = $this->postJson('/api/v1/admin/careers', ['faculty_id' => $faculty->getKey(), 'name' => 'Software', 'code' => 'SW-01', 'cycles_count' => 4])
            ->assertCreated()->json('data.id');

        $this->getJson('/api/v1/admin/careers')->assertOk()->assertJsonPath('data.0.cycle_levels', 4);

        $this->patchJson('/api/v1/admin/careers/'.$careerId, ['cycles_count' => 6])->assertOk();
        $this->assertSame(range(1, 6), Ciclo::query()->where('fk_carrera', $careerId)->orderBy('numero')->pluck('numero')->all());
        $this->assertSame('Sexto ciclo', Ciclo::query()->where('fk_carrera', $careerId)->where('numero', 6)->value('nombre'));

        // Repetir el mismo valor no duplica ciclos.
        $this->patchJson('/api/v1/admin/careers/'.$careerId, ['cycles_count' => 6])->assertOk();
        $this->assertSame(6, Ciclo::query()->where('fk_carrera', $careerId)->count());

        // Bajar la cantidad o pasar el máximo se rechaza.
        $this->patchJson('/api/v1/admin/careers/'.$careerId, ['cycles_count' => 3])->assertUnprocessable()->assertJsonValidationErrors('cycles_count');
        $this->patchJson('/api/v1/admin/careers/'.$careerId, ['cycles_count' => 13])->assertUnprocessable()->assertJsonValidationErrors('cycles_count');
        $this->assertSame(6, Ciclo::query()->where('fk_carrera', $careerId)->count());
    }

    public function test_career_code_is_required_on_create_and_cannot_be_emptied_on_update(): void
    {
        $this->actingAsAdministrator();
        $faculty = Facultad::query()->create(['nombre' => 'Facultad de Ciencias', 'estado' => true]);

        $this->postJson('/api/v1/admin/careers', [
            'faculty_id' => $faculty->getKey(),
            'name' => 'Matemática',
        ])->assertUnprocessable()->assertJsonValidationErrors(['code' => 'El campo código de carrera es obligatorio.']);
        $this->postJson('/api/v1/admin/careers', [
            'faculty_id' => $faculty->getKey(),
            'name' => 'Matemática',
            'code' => str_repeat('M', 31),
        ])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertDatabaseCount('carrera', 0);

        $careerId = $this->postJson('/api/v1/admin/careers', [
            'faculty_id' => $faculty->getKey(),
            'name' => 'Matemática',
            'code' => '  mat-2019  ',
        ])->assertCreated()->assertJsonPath('data.code', 'MAT-2019')->json('data.id');

        $this->patchJson('/api/v1/admin/careers/'.$careerId, ['code' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->patchJson('/api/v1/admin/careers/'.$careerId, ['code' => 'MAT-2024'])
            ->assertOk()->assertJsonPath('data.code', 'MAT-2024');
        $this->assertDatabaseHas('carrera', ['id_carrera' => $careerId, 'codigo' => 'MAT-2024']);

        // Un cambio que no incluye el código conserva el actual.
        $this->patchJson('/api/v1/admin/careers/'.$careerId, ['name' => 'Matemática Aplicada'])
            ->assertOk()->assertJsonPath('data.code', 'MAT-2024');
    }

    public function test_career_code_is_unique_across_all_careers(): void
    {
        $this->actingAsAdministrator();
        $faculty = Facultad::query()->create(['nombre' => 'Facultad de Ciencias', 'estado' => true]);
        $other = Facultad::query()->create(['nombre' => 'Facultad de Salud', 'estado' => true]);
        $career = Carrera::query()->create(['fk_facultad' => $faculty->getKey(), 'nombre' => 'Física', 'codigo' => 'FIS-01', 'estado' => true]);
        $second = Carrera::query()->create(['fk_facultad' => $faculty->getKey(), 'nombre' => 'Química', 'codigo' => 'QUI-01', 'estado' => true]);

        $this->postJson('/api/v1/admin/careers', [
            'faculty_id' => $other->getKey(),
            'name' => 'Enfermería',
            'code' => 'FIS-01',
        ])->assertUnprocessable()->assertJsonValidationErrors(['code' => 'Ya existe una carrera con este código. El código de carrera es único institucionalmente.']);

        // El código se guarda en mayúsculas: escrito en minúsculas también choca.
        $this->postJson('/api/v1/admin/careers', [
            'faculty_id' => $other->getKey(),
            'name' => 'Enfermería',
            'code' => ' fis-01 ',
        ])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson('/api/v1/admin/careers', [
            'faculty_id' => $other->getKey(),
            'name' => 'Enfermería',
            'code' => 'enf-01',
        ])->assertCreated()->assertJsonPath('data.code', 'ENF-01');

        $this->patchJson('/api/v1/admin/careers/'.$second->getKey(), ['code' => 'FIS-01'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        // Guardar la carrera con su propio código no choca consigo misma.
        $this->patchJson('/api/v1/admin/careers/'.$career->getKey(), ['name' => 'Física', 'code' => 'FIS-01'])
            ->assertOk();
        $this->assertSame(1, Carrera::query()->where('codigo', 'FIS-01')->count());
    }

    public function test_a_career_may_repeat_its_name_only_with_a_different_code(): void
    {
        $this->actingAsAdministrator();
        $faculty = Facultad::query()->create(['nombre' => 'Facultad de Ciencias', 'estado' => true]);
        $legacy = Carrera::query()->create(['fk_facultad' => $faculty->getKey(), 'nombre' => 'Matemática', 'estado' => true]);
        Carrera::query()->create(['fk_facultad' => $faculty->getKey(), 'nombre' => 'Física', 'codigo' => 'FIS-01', 'estado' => true]);

        // Otra malla de la misma carrera: mismo nombre, código distinto.
        $this->postJson('/api/v1/admin/careers', [
            'faculty_id' => $faculty->getKey(),
            'name' => 'Matemática',
            'code' => 'MAT-2024',
        ])->assertCreated()->assertJsonPath('data.name', 'Matemática')->assertJsonPath('data.code', 'MAT-2024');
        $this->postJson('/api/v1/admin/careers', [
            'faculty_id' => $faculty->getKey(),
            'name' => 'Física',
            'code' => 'FIS-02',
        ])->assertCreated();

        // Mismo nombre y mismo código: rechazado.
        $this->postJson('/api/v1/admin/careers', [
            'faculty_id' => $faculty->getKey(),
            'name' => 'Física',
            'code' => 'FIS-01',
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'name' => 'Ya existe una carrera con este nombre y este código. Para registrar otra malla de la carrera, usa un código distinto.',
            'code',
        ]);

        // Una carrera existente sin código puede recibirlo al editarla.
        $this->patchJson('/api/v1/admin/careers/'.$legacy->getKey(), ['name' => 'Matemática', 'code' => 'MAT-2019'])
            ->assertOk()->assertJsonPath('data.code', 'MAT-2019');

        $this->assertSame(2, Carrera::query()->where('nombre', 'Matemática')->count());
        $this->assertSame(2, Carrera::query()->where('nombre', 'Física')->count());
    }

    public function test_career_lists_expose_the_code(): void
    {
        $this->actingAsAdministrator();
        $faculty = Facultad::query()->create(['nombre' => 'Facultad de Ciencias', 'estado' => true]);
        Carrera::query()->create(['fk_facultad' => $faculty->getKey(), 'nombre' => 'Física', 'codigo' => 'FIS-01', 'estado' => true]);
        Carrera::query()->create(['fk_facultad' => $faculty->getKey(), 'nombre' => 'Química', 'estado' => true]);

        $this->getJson('/api/v1/admin/careers')->assertOk()
            ->assertJsonPath('data.0.name', 'Física')
            ->assertJsonPath('data.0.code', 'FIS-01')
            ->assertJsonPath('data.1.code', null);
        $this->getJson('/api/v1/admin/careers?all=1')->assertOk()
            ->assertJsonPath('data.0.code', 'FIS-01');
        $this->getJson('/api/v1/admin/careers?search=fis-0')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'FIS-01');
    }

    public function test_career_import_accepts_an_optional_code_column(): void
    {
        $this->actingAsAdministrator();
        $faculty = Facultad::query()->create(['nombre' => 'Facultad de Ciencias', 'estado' => true]);
        Carrera::query()->create(['fk_facultad' => $faculty->getKey(), 'nombre' => 'Física', 'codigo' => 'FIS-01', 'estado' => true]);

        $id = $this->post('/api/v1/imports/careers', [
            'file' => UploadedFile::fake()->createWithContent('carreras.csv', "facultad,nombre,codigo\nFacultad de Ciencias,Física,FIS-02\nFacultad de Ciencias,Física,FIS-01\nFacultad de Ciencias,Química,\n"),
        ], ['Accept' => 'application/json'])->assertSuccessful()->json('data.id');

        $this->getJson('/api/v1/imports/'.$id)
            ->assertJsonPath('data.created_count', 2)
            ->assertJsonPath('data.skipped_count', 1)
            ->assertJsonPath('data.warnings.0.row', 3);
        $this->assertSame(['FIS-01', 'FIS-02'], Carrera::query()->where('nombre', 'Física')->orderBy('codigo')->pluck('codigo')->all());
        $this->assertNull(Carrera::query()->where('nombre', 'Química')->value('codigo'));
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

        Sanctum::actingAs(Usuario::factory()->withRole('estudiante')->create(), ['*']);

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

        // Mismo número en la misma carrera: rechazado, el número de ciclo es único por carrera.
        $this->postJson('/api/v1/admin/cycles', [
            'career_id' => $career->getKey(),
            'name' => 'Primer ciclo duplicado',
            'number' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('number');

        // Número distinto: permitido.
        $cycle2Id = $this->postJson('/api/v1/admin/cycles', [
            'career_id' => $career->getKey(),
            'name' => 'Segundo ciclo',
            'number' => 2,
        ])->assertCreated()
            ->assertJsonPath('data.number', 2)
            ->json('data.id');

        $this->patchJson('/api/v1/admin/cycles/'.$cycle->getKey(), [
            'name' => 'Ciclo inicial',
            'number' => 3,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Ciclo inicial')
            ->assertJsonPath('data.number', 3);

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
            'code' => 'IS-01',
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
            'code' => 'MP-01',
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
        Sanctum::actingAs(Usuario::factory()->withRole('estudiante')->create(), ['*']);

        $this->postJson('/api/v1/admin/sections', ['name' => 'Paralelo A'])->assertForbidden();
        $this->getJson('/api/v1/admin/sections')->assertForbidden();
    }

    private function actingAsAdministrator(): Usuario
    {
        $administrator = Usuario::factory()->withRole('administrador')->create();
        Sanctum::actingAs($administrator, ['*']);

        return $administrator;
    }
}

<?php

namespace Tests\Feature\Api\V1\Imports;

use App\Jobs\ProcessBulkImport;
use App\Models\BulkImport;
use App\Models\Carrera;
use App\Models\Ciclo;
use App\Models\Facultad;
use App\Models\Paralelo;
use App\Models\PeriodoAcademico;
use App\Models\Subject;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Api\V1\Tutoring\TutoringTestCase;

class CatalogImportTest extends TutoringTestCase
{
    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Usuario::factory()->withRole('administrador')->create();
    }

    public function test_admin_imports_faculties_reporting_invalid_and_duplicate_rows(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $response = $this->upload('faculties', "\xEF\xBB\xBFNombre\nFacultad de Salud\n\nCiencias\nFacultad de Salud\nFacultad de Educación\n")
            ->assertAccepted()
            ->assertJsonPath('data.total_rows', 4);

        $this->getJson('/api/v1/imports/'.$response->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.status', BulkImport::STATUS_DONE)
            ->assertJsonPath('data.total_rows', 4)
            ->assertJsonPath('data.created_count', 2)
            ->assertJsonPath('data.failed_count', 2)
            ->assertJsonPath('data.errors.0.row', 4)
            ->assertJsonPath('data.errors.1.row', 5);

        $this->assertDatabaseHas('facultad', ['nombre' => 'Facultad de Educación']);
        $this->assertNull(BulkImport::query()->findOrFail($response->json('data.id'))->rows);
    }

    public function test_upload_queues_the_import_and_stores_rows_encrypted(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->admin, ['*']);

        $id = $this->upload('faculties', "nombre\nFacultad Secreta\n")
            ->assertAccepted()
            ->assertJsonPath('data.status', BulkImport::STATUS_PENDING)
            ->json('data.id');

        Queue::assertPushed(ProcessBulkImport::class);
        $raw = (string) DB::table('bulk_imports')->where('id', $id)->value('rows');
        $this->assertStringNotContainsString('Facultad Secreta', $raw);
        $this->assertSame('Facultad Secreta', BulkImport::query()->findOrFail($id)->rows[0]['values']['nombre']);
    }

    public function test_reader_accepts_semicolons_and_windows_1252_encoding(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $this->upload('faculties', mb_convert_encoding("nombre;\nFacultad de Agronomía;\n", 'Windows-1252', 'UTF-8'))
            ->assertAccepted();

        $this->assertDatabaseHas('facultad', ['nombre' => 'Facultad de Agronomía']);
    }

    public function test_file_without_required_columns_is_rejected(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $this->upload('careers', "nombre\nSoftware 2\n")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
        $this->assertDatabaseCount('bulk_imports', 0);
    }

    public function test_admin_imports_careers_resolving_names_without_accents_or_case(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $response = $this->upload('careers', "facultad,nombre,ciclos,modalidad\nCIENCIAS,Electrónica,2,presencial\nInexistente,Medicina,,\n");

        $this->getJson('/api/v1/imports/'.$response->json('data.id'))
            ->assertJsonPath('data.created_count', 1)
            ->assertJsonPath('data.errors.0.row', 3)
            ->assertJsonPath('data.errors.0.messages.0', 'La facultad «Inexistente» no existe o está inactiva.');

        $career = Carrera::query()->where('nombre', 'Electrónica')->firstOrFail();
        $this->assertSame($this->modality->getKey(), $career->fk_modalidad);
        $this->assertSame(2, Ciclo::query()->where('fk_carrera', $career->getKey())->count());
    }

    public function test_admin_imports_cycles_and_academic_periods(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $this->upload('cycles', "carrera,numero,nombre\nSoftware,9,Noveno ciclo\nSoftware,abc,Malo\n");
        $this->assertDatabaseHas('ciclo', ['fk_carrera' => $this->career->getKey(), 'numero' => 9, 'nombre' => 'Noveno ciclo']);

        $response = $this->upload('academic-periods', "nombre,fecha_inicio,fecha_fin\n2027-1S,2027-04-01,2027-08-31\n2027-2S,2027-12-01,2027-09-01\n");
        $this->getJson('/api/v1/imports/'.$response->json('data.id'))
            ->assertJsonPath('data.created_count', 1)
            ->assertJsonPath('data.failed_count', 1);
        $this->assertDatabaseHas('periodo_academico', ['nombre' => '2027-1S', 'estado' => false]);
    }

    public function test_coordinator_imports_subjects_only_in_coordinated_careers(): void
    {
        Sanctum::actingAs($this->coordinator, ['*']);

        $response = $this->upload('subjects', "carrera,nombre,codigo,ciclo\nsoftware,Programación I,SW-101,1\nAdministración,Contabilidad,,\nSoftware,Redes,,7\n");

        $this->getJson('/api/v1/imports/'.$response->json('data.id'))
            ->assertJsonPath('data.created_count', 1)
            ->assertJsonPath('data.errors.0.messages.0', 'No tienes permiso para registrar este elemento.')
            ->assertJsonPath('data.errors.1.messages.0', 'El ciclo «7» no existe en la carrera indicada.');

        $subject = Subject::query()->where('code', 'SW-101')->firstOrFail();
        $this->assertSame([$this->cycle->getKey()], $subject->cycles()->pluck('ciclo.id_ciclo')->all());
    }

    public function test_degree_coordinator_imports_sections_into_current_period(): void
    {
        Sanctum::actingAs(Usuario::factory()->withRole('coordinador_titulacion')->create(), ['*']);

        $this->upload('sections', "nombre\nZ1\n")->assertAccepted();

        $section = Paralelo::query()->where('nombre', 'Z1')->firstOrFail();
        $this->assertTrue(PeriodoAcademico::periodoVigente()?->paralelos()->whereKey($section->getKey())->exists());
    }

    public function test_imports_are_authorized_and_private_to_their_author(): void
    {
        Sanctum::actingAs($this->coordinator, ['*']);
        $this->upload('faculties', "nombre\nX\n")->assertForbidden();
        $this->getJson('/api/v1/imports/faculties/template')->assertForbidden();
        $this->getJson('/api/v1/imports/unknown/template')->assertNotFound();

        Sanctum::actingAs($this->admin, ['*']);
        $id = $this->upload('faculties', "nombre\nFacultad Privada\n")->json('data.id');
        $this->get('/api/v1/imports/careers/template')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertSee('facultad;nombre;ciclos;modalidad', false);

        Sanctum::actingAs(Usuario::factory()->withRole('administrador')->create(), ['*']);
        $this->getJson('/api/v1/imports/'.$id)->assertNotFound();
        $this->assertSame(1, Facultad::query()->where('nombre', 'Facultad Privada')->count());
    }

    private function upload(string $type, string $contents): TestResponse
    {
        return $this->post('/api/v1/imports/'.$type, [
            'file' => UploadedFile::fake()->createWithContent('datos.csv', $contents),
        ], ['Accept' => 'application/json']);
    }
}

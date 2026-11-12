<?php

namespace Tests\Feature\Api\V1\Tutoring;

use App\Imports\CurriculumFile;
use App\Models\Ciclo;
use App\Models\Subject;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use ZipArchive;

/** La malla es la carrera: subirla crea sus asignaturas, ciclos y previas. */
class CurriculumTest extends TutoringTestCase
{
    private const CSV = "codigo;nombre;ciclo;previas\n"
        ."N-B1-001;Algoritmos;1;\n"
        ."N-B2-002;Programación I;2;N-B1-001\n"
        ."N-B3-003;Programación II;3;Programación I\n"
        ."N-B3-004;Redes;3;\n";

    private Ciclo $second;

    protected function setUp(): void
    {
        parent::setUp();
        $section = $this->cycle->paralelo;
        $this->second = $this->createCycle($this->career, $section, 2);
        Sanctum::actingAs($this->coordinator, ['*']);
    }

    /** @return list<array<string, mixed>> */
    private function preview(string $csv): array
    {
        return $this->post(self::API.'/curricula/preview', [
            'file' => UploadedFile::fake()->createWithContent('malla.csv', $csv), 'career_id' => $this->career->getKey(),
        ], ['Accept' => 'application/json'])->assertOk()->json('data.subjects');
    }

    /** @return list<array<string, mixed>> */
    private function rows(string $csv = self::CSV): array
    {
        return array_map(fn (array $row): array => collect($row)->except('errors')->all(), $this->preview($csv));
    }

    public function test_the_csv_preview_reads_subjects_and_flags_errors(): void
    {
        $this->createSubject($this->cycle, 'OLD-1', 'Cálculo');
        $rows = $this->preview(self::CSV."OLD-1;Estadística;II;\nN-B2-009;calculo;2;\n;Física;1;Química\n");

        $this->assertSame(['code' => 'N-B2-002', 'name' => 'PROGRAMACION I', 'cycle' => 2, 'previous' => ['N-B1-001'], 'errors' => []], $rows[1]);
        $this->assertSame('PROGRAMACION II', $rows[2]['name']);
        // Lo que ya existe en la carrera no se vuelve a crear; «II» es el ciclo 2.
        $this->assertSame(['code' => 'OLD-1', 'name' => 'ESTADISTICA', 'cycle' => 2, 'previous' => [], 'errors' => ['El código ya existe en la carrera.']], $rows[4]);
        $this->assertSame(['La asignatura ya existe en la carrera.'], $rows[5]['errors']);
        $this->assertSame(['La previa «Química» no está en la malla.'], $rows[6]['errors']);
    }

    public function test_a_previous_subject_may_be_an_existing_subject_of_an_earlier_cycle(): void
    {
        $this->createSubject($this->cycle, 'OLD-1', 'Cálculo');
        $rows = $this->preview("codigo;nombre;ciclo;previas\nN-B2-010;Física;2;OLD-1\nN-B1-011;Química;1;Cálculo\n");

        $this->assertSame([], $rows[0]['errors']);
        $this->assertSame(['La previa «Cálculo» debe ser de un ciclo anterior.'], $rows[1]['errors']);
    }

    public function test_an_excel_file_is_read_like_the_csv(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'malla').'.xlsx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('xl/sharedStrings.xml', '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>Código</t></si><si><t>Nombre</t></si><si><t>Ciclo</t></si><si><t>Previas</t></si><si><t>Algoritmos</t></si><si><t>Programación I</t></si></sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .'<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c><c r="D1" t="s"><v>3</v></c></row>'
            .'<row r="2"><c r="A2" t="inlineStr"><is><t>X-1</t></is></c><c r="B2" t="s"><v>4</v></c><c r="C2"><v>1</v></c></row>'
            .'<row r="3"><c r="B3" t="s"><v>5</v></c><c r="C3"><v>2</v></c><c r="D3" t="inlineStr"><is><t>X-1</t></is></c></row>'
            .'</sheetData></worksheet>');
        $zip->close();

        $rows = $this->post(self::API.'/curricula/preview', ['file' => new UploadedFile($path, 'malla.xlsx', null, null, true), 'career_id' => $this->career->getKey()], ['Accept' => 'application/json'])
            ->assertOk()->json('data.subjects');
        @unlink($path);

        $this->assertSame(['X-1', null], array_column($rows, 'code'));
        $this->assertSame(['ALGORITMOS', 'PROGRAMACION I'], array_column($rows, 'name'));
        $this->assertSame([[], ['X-1']], array_column($rows, 'previous'));
    }

    public function test_pdf_text_yields_codes_their_cycle_and_names_on_the_same_line(): void
    {
        $rows = CurriculumFile::parsePdfText("SW-B1-001\tSW-B1-002\n48 48 48 3\nSW-B2-006 PROGRAMACIÓN ORIENTADA A OBJETOS 3 SW-B1-001\n");

        $this->assertSame([
            ['code' => 'SW-B1-001', 'name' => '', 'cycle' => 1, 'previous' => []],
            ['code' => 'SW-B1-002', 'name' => '', 'cycle' => 1, 'previous' => []],
            ['code' => 'SW-B2-006', 'name' => 'PROGRAMACIÓN ORIENTADA A OBJETOS', 'cycle' => 2, 'previous' => ['SW-B1-001']],
        ], $rows);
    }

    public function test_importing_the_malla_creates_the_career_subjects_cycles_and_previous_subjects(): void
    {
        $existing = $this->createSubject($this->cycle, 'OLD-1', 'Cálculo');
        $subjects = [...$this->rows(), ['code' => null, 'name' => 'Física', 'cycle' => 4, 'previous' => ['OLD-1', 'Redes']]];

        $this->postJson(self::API.'/curricula', ['career_id' => $this->career->getKey(), 'subjects' => $subjects])
            ->assertCreated()->assertExactJson(['created' => 5]);

        $this->assertSame(6, Subject::query()->where('career_id', $this->career->getKey())->count());
        $two = Subject::query()->where('code', 'N-B2-002')->firstOrFail();
        $this->assertSame('PROGRAMACION I', $two->name);
        $this->assertTrue($two->is_active);
        $this->assertSame($this->modality->getKey(), $two->modality_id);
        // La asignatura queda en su número de ciclo, sin vincularse a paralelos.
        $this->assertSame(2, $two->cycle_number);
        $this->assertSame([], $two->cycles()->pluck('ciclo.id_ciclo')->all());
        $this->assertSame(['PROGRAMACION II'], $two->nextSubjects()->pluck('name')->all());
        $this->assertSame(['ALGORITMOS'], $two->previousSubjects()->pluck('name')->all());
        // Un ciclo que la carrera aún no tiene se crea.
        $physics = Subject::query()->where('name', 'FISICA')->firstOrFail();
        $this->assertSame(4, $physics->cycle_number);
        $this->assertTrue(Ciclo::query()->where('fk_carrera', $this->career->getKey())->where('numero', 4)->exists());
        $this->assertSame(['Cálculo', 'REDES'], $physics->previousSubjects()->orderBy('subjects.id')->pluck('name')->all());
        $this->assertSame(['FISICA'], $existing->nextSubjects()->pluck('name')->all());
    }

    public function test_the_import_rejects_codes_or_names_that_already_exist_in_the_career(): void
    {
        $this->createSubject($this->cycle, 'N-B1-001', 'Otra');
        $this->createSubject($this->cycle, 'OLD-2', 'Redes');
        $this->createSubject($this->otherCycle, 'N-B2-002', 'Programación I');

        $this->postJson(self::API.'/curricula', ['career_id' => $this->career->getKey(), 'subjects' => $this->rows()])
            ->assertUnprocessable()->assertJsonValidationErrors(['subjects' => 'Hay 2 asignaturas con errores; corrígelas antes de importar la malla.']);
        $this->postJson(self::API.'/curricula', ['career_id' => $this->career->getKey(), 'subjects' => [['name' => 'Redes', 'cycle' => 2, 'previous' => ['Física']]]])
            ->assertUnprocessable()->assertJsonValidationErrors(['subjects' => 'Hay 1 asignatura con errores; corrígela antes de importar la malla.']);

        $this->assertSame(2, Subject::query()->where('career_id', $this->career->getKey())->count());
    }

    public function test_only_the_coordinator_of_the_career_uploads_its_malla(): void
    {
        $subjects = $this->rows();
        Sanctum::actingAs(Usuario::factory()->withRole('coordinador_carrera')->create(), ['*']);

        $this->post(self::API.'/curricula/preview', ['file' => UploadedFile::fake()->createWithContent('malla.csv', self::CSV), 'career_id' => $this->career->getKey()], ['Accept' => 'application/json'])
            ->assertForbidden();
        $this->postJson(self::API.'/curricula', ['career_id' => $this->career->getKey(), 'subjects' => $subjects])->assertForbidden();
        Sanctum::actingAs(Usuario::factory()->withRole('docente')->create(), ['*']);
        $this->postJson(self::API.'/curricula', ['career_id' => $this->career->getKey(), 'subjects' => $subjects])->assertForbidden();

        $this->assertSame(0, Subject::query()->count());
    }

    public function test_curriculum_versions_no_longer_exist(): void
    {
        $this->getJson(self::API.'/curricula?career_id='.$this->career->getKey())->assertMethodNotAllowed();
        $this->deleteJson(self::API.'/curricula/1')->assertNotFound();
        $this->assertFalse(Schema::hasTable('curricula'));
        $this->assertFalse(Schema::hasColumn('subjects', 'curriculum_id'));
        $this->assertFalse(Schema::hasColumn('usuario', 'curriculum_id'));

        $this->postJson(self::API.'/subjects', ['career_id' => $this->career->getKey(), 'name' => 'Redes', 'cycle_id' => $this->cycle->getKey()])
            ->assertCreated();
    }
}

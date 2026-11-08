<?php

namespace Tests\Feature\Api\V1\Tutoring;

use App\Actions\Tutoring\AdvanceApprovedStudents;
use App\Imports\CurriculumFile;
use App\Models\AsignaturaTutoria;
use App\Models\Ciclo;
use App\Models\Curriculum;
use App\Models\InscripcionTutoria;
use App\Models\PeriodoAcademico;
use App\Models\Subject;
use App\Models\Usuario;
use App\Support\Curricula;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use ZipArchive;

class CurriculumTest extends TutoringTestCase
{
    private const CSV = "codigo;nombre;ciclo;previas\n"
        ."N-B1-001;Algoritmos;1;\n"
        ."N-B2-002;Programación I;2;N-B1-001\n"
        ."N-B3-003;Programación II;3;Programación I\n"
        ."N-B3-004;Redes;3;\n";

    private PeriodoAcademico $next;

    private Ciclo $second;

    private Ciclo $fourth;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo('2026-06-01');
        $this->next = PeriodoAcademico::query()->create(['nombre' => 'PAO 2027-1', 'fecha_inicio' => '2026-11-15', 'fecha_fin' => '2027-03-31', 'estado' => false]);
        $section = $this->cycle->paralelo;
        $this->second = $this->createCycle($this->career, $section, 2);
        $this->createCycle($this->career, $section, 3);
        $this->fourth = $this->createCycle($this->career, $section, 4);
        Sanctum::actingAs($this->coordinator, ['*']);
    }

    private function student(Ciclo $cycle): Usuario
    {
        $student = Usuario::factory()->withRole('estudiante')->create(['fk_ciclo' => $cycle->getKey()]);
        $student->paralelos()->attach($cycle->fk_paralelo, ['fecha_asignacion' => today(), 'estado' => true]);

        return $student;
    }

    /** @return array<string, mixed> */
    private function preview(UploadedFile $file): array
    {
        return $this->post(self::API.'/curricula/preview', ['file' => $file, 'career_id' => $this->career->getKey()], ['Accept' => 'application/json'])
            ->assertOk()->json('data');
    }

    private function createCurriculum(int $upToCycle = 2): Curriculum
    {
        $subjects = collect($this->preview(UploadedFile::fake()->createWithContent('malla.csv', self::CSV))['subjects'])
            ->map(fn (array $row): array => collect($row)->except('errors')->all())->all();
        $id = $this->postJson(self::API.'/curricula', [
            'career_id' => $this->career->getKey(), 'name' => 'Malla 2027', 'period_id' => $this->next->getKey(),
            'up_to_cycle' => $upToCycle, 'subjects' => $subjects,
        ])->assertCreated()->assertJsonPath('data.subjects_count', 4)->json('data.id');

        return Curriculum::query()->findOrFail($id);
    }

    private function tutoring(Subject $subject, Ciclo $cycle, PeriodoAcademico $period): AsignaturaTutoria
    {
        return AsignaturaTutoria::query()->create([
            'subject_id' => $subject->getKey(), 'fk_ciclo' => $cycle->getKey(), 'fk_periodo' => $period->getKey(),
            'fk_modalidad' => $this->modality->getKey(), 'fk_paralelo' => $cycle->fk_paralelo, 'nombre' => $subject->name, 'estado' => true,
        ]);
    }

    /** @return list<int> */
    private function available(AsignaturaTutoria $tutoring): array
    {
        return collect($this->getJson(self::API."/tutorings/{$tutoring->getKey()}/available-students")->assertOk()->json('data'))->pluck('id')->sort()->values()->all();
    }

    public function test_the_csv_preview_reads_subjects_and_flags_errors(): void
    {
        $this->createSubject($this->cycle, 'OLD-1', 'Cálculo');
        $rows = $this->preview(UploadedFile::fake()->createWithContent('malla.csv', self::CSV."OLD-1;;II;\n;Física;1;Química\n"))['subjects'];

        $this->assertSame(['code' => 'N-B2-002', 'name' => 'PROGRAMACION I', 'cycle' => 2, 'previous' => ['N-B1-001'], 'errors' => []], $rows[1]);
        $this->assertSame('PROGRAMACION II', $rows[2]['name']);
        // El nombre de un código ya registrado se completa; «II» es el ciclo 2.
        $this->assertSame(['code' => 'OLD-1', 'name' => 'CALCULO', 'cycle' => 2, 'previous' => [], 'errors' => []], $rows[4]);
        $this->assertSame(['La previa «Química» no está en la malla.'], $rows[5]['errors']);
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

        $rows = $this->preview(new UploadedFile($path, 'malla.xlsx', null, null, true))['subjects'];

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

    public function test_a_new_curriculum_applies_to_students_up_to_the_chosen_cycle_from_its_period(): void
    {
        $base = Curricula::base($this->career->getKey());
        $oldSubject = $this->createSubject($this->second, 'OLD-2', 'Programación I');
        $early = $this->student($this->second);
        $late = $this->student($this->fourth);

        $curriculum = $this->createCurriculum();

        $this->assertNull($early->refresh()->curriculum_id);
        $this->assertSame($base->id, $late->refresh()->curriculum_id);
        $new = Subject::query()->where('curriculum_id', $curriculum->id)->where('code', 'N-B2-002')->firstOrFail();
        $this->assertSame([$this->second->getKey()], $new->cycles()->pluck('ciclo.id_ciclo')->all());
        $this->assertSame(['PROGRAMACION II'], $new->nextSubjects()->pluck('name')->all());

        $this->getJson(self::API.'/curricula?career_id='.$this->career->getKey())->assertOk()
            ->assertJsonPath('data.0.name', 'Malla 2027')
            ->assertJsonPath('data.0.from_period_name', 'PAO 2027-1')
            ->assertJsonPath('data.0.up_to_cycle', 2)
            ->assertJsonPath('data.0.previous_name', 'Malla inicial')
            ->assertJsonPath('data.0.in_force', false)
            ->assertJsonPath('data.1.in_force', true)
            ->assertJsonPath('periods.0.name', 'PAO 2026');
        $this->getJson(self::API.'/subjects?per_page=50&curriculum_id='.$curriculum->id)->assertOk()->assertJsonCount(4, 'data');

        // PAO actual: todos con la malla inicial. Desde el PAO nuevo: 2.º con la nueva, 4.º con la anterior.
        $this->assertSame(collect([$early->getKey(), $late->getKey()])->sort()->values()->all(), $this->available($this->tutoring($oldSubject, $this->second, $this->period)));
        $this->assertSame([$early->getKey()], $this->available($this->tutoring($new, $this->second, $this->next)));
        $this->assertSame([$late->getKey()], $this->available($this->tutoring($oldSubject, $this->fourth, $this->next)));

        // Quien ingresa después sigue la malla que rija.
        $newcomer = $this->student($this->second);
        $this->assertNull($newcomer->curriculum_id);
    }

    public function test_previous_subjects_approved_with_the_old_curriculum_count_for_the_new_one(): void
    {
        $oldOne = $this->createSubject($this->cycle, 'OLD-1', 'Programación I');
        $student = $this->student($this->second);
        InscripcionTutoria::query()->create([
            'fk_asig_tutoria' => $this->tutoring($oldOne, $this->cycle, $this->period)->getKey(), 'fk_id_usuario' => $student->getKey(),
            'fecha_inscripcion' => today(), 'estado' => true, 'final_result' => InscripcionTutoria::APPROVED,
        ]);
        $curriculum = $this->createCurriculum(3);
        $three = Ciclo::query()->where('fk_carrera', $this->career->getKey())->where('numero', 3)->firstOrFail();
        $next = $this->tutoring(Subject::query()->where('curriculum_id', $curriculum->id)->where('code', 'N-B3-003')->firstOrFail(), $three, $this->next);

        app(AdvanceApprovedStudents::class)->intoNewTutoring($next);

        $this->assertDatabaseHas('inscripcion_tutoria', ['fk_asig_tutoria' => $next->getKey(), 'fk_id_usuario' => $student->getKey()]);
    }

    public function test_the_create_request_is_validated(): void
    {
        $payload = ['career_id' => $this->career->getKey(), 'name' => 'Malla 2027', 'period_id' => $this->next->getKey(), 'up_to_cycle' => 2];

        $this->postJson(self::API.'/curricula', $payload + ['subjects' => [['name' => 'Redes', 'cycle' => 2, 'previous' => ['Física']]]])
            ->assertUnprocessable()->assertJsonValidationErrors(['subjects' => 'Hay 1 asignatura con errores; corrígela antes de crear la malla.']);
        $past = PeriodoAcademico::query()->create(['nombre' => 'PAO 2025', 'fecha_inicio' => '2025-05-01', 'fecha_fin' => '2025-10-31', 'estado' => false]);
        $this->postJson(self::API.'/curricula', ['period_id' => $past->getKey(), 'subjects' => [['name' => 'Redes', 'cycle' => 1]]] + $payload)
            ->assertUnprocessable()->assertJsonValidationErrors(['period_id' => 'Elige el PAO vigente o uno futuro.']);

        Sanctum::actingAs(Usuario::factory()->withRole('coordinador_carrera')->create(), ['*']);
        $this->postJson(self::API.'/curricula', $payload + ['subjects' => [['name' => 'Redes', 'cycle' => 1]]])->assertForbidden();
    }

    public function test_a_curriculum_without_tutorings_can_be_deleted_but_not_the_initial_one(): void
    {
        $curriculum = $this->createCurriculum();
        $base = Curricula::base($this->career->getKey());

        $this->deleteJson(self::API."/curricula/{$base->id}")->assertUnprocessable();
        $this->tutoring($curriculum->subjects()->firstOrFail(), $this->cycle, $this->next);
        $this->deleteJson(self::API."/curricula/{$curriculum->id}")->assertUnprocessable()
            ->assertJsonValidationErrors(['curriculum' => 'No puedes eliminar esta malla: ya hay tutorías de sus asignaturas.']);

        AsignaturaTutoria::query()->delete();
        $this->deleteJson(self::API."/curricula/{$curriculum->id}")->assertNoContent();
        $this->assertDatabaseMissing('curricula', ['id' => $curriculum->id]);
        $this->assertSame(0, Subject::query()->where('code', 'N-B1-001')->count());
    }
}

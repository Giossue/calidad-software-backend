<?php

namespace Tests\Feature\Api\V1\Tutoring;

use App\Imports\RosterPdf;
use App\Models\Malla;
use App\Models\Subject;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

/** El listado se carga en la tutoría de una malla: quien sigue otra malla no se inscribe y los nuevos siguen esa malla. */
class MallaRosterImportTest extends TutoringTestCase
{
    private const TEXT = "UNIVERSIDAD ESTATAL DE BOLÍVAR\nREGISTRO ACTIVIDADES ACADÉMICAS\n"
        ."Carrera: SOFTWARE\tFecha: ____________\n"
        ."Docente: PEREZ MORA LUIS\tHora: ____________\n"
        ."Asignatura: ALGORITMOS\tParalelo: A\n"
        ."Nº APELLIDOS Y NOMBRES\tCORREO\tTELÉFONO\tFIRMA\n"
        ."1 Andrade Vera Ana Lucia\tana.andrade@ueb.edu.ec 0991112233\n"
        ."2 Benitez Cruz Bruno\tbruno.benitez@ueb.edu.ec 0992223344\n";

    private Malla $old;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->old = Malla::query()->create(['career_id' => $this->career->getKey(), 'name' => 'Malla 2016', 'is_current' => false]);
        Subject::query()->create(['malla_id' => $this->old->getKey(), 'code' => 'OLD-1', 'name' => 'ALGORITMOS', 'cycle_number' => 1, 'modality_id' => $this->modality->getKey(), 'is_active' => true]);
        $this->app->instance(RosterPdf::class, new class(self::TEXT) extends RosterPdf
        {
            public function __construct(private string $text) {}

            public function read(string $path): array
            {
                return self::parseText($this->text);
            }
        });
        Sanctum::actingAs($this->coordinator, ['*']);
    }

    private function upload(array $extra = [])
    {
        return $this->post(self::API.'/roster-imports', [
            'file' => UploadedFile::fake()->create('LISTADO.pdf', 20, 'application/pdf'),
            'career_id' => $this->career->getKey(),
            ...$extra,
        ], ['Accept' => 'application/json']);
    }

    public function test_students_of_another_malla_are_blocked_and_new_ones_follow_the_tutoring_malla(): void
    {
        $ana = Usuario::factory()->withRole('estudiante')->create(['correo' => 'ana.andrade@ueb.edu.ec', 'fk_carrera' => $this->career->getKey()]);
        $this->assertSame($this->currentMalla()->getKey(), $ana->malla_id);

        $this->upload(['malla_id' => $this->old->getKey()])->assertOk()
            ->assertJsonPath('data.subject.code', 'OLD-1')
            ->assertJsonPath('data.students.0.status', 'blocked')
            ->assertJsonPath('data.students.0.message', 'El estudiante sigue otra malla.')
            ->assertJsonPath('data.students.1.status', 'new');

        $this->upload(['malla_id' => $this->old->getKey(), 'confirm' => '1'])->assertOk()->assertJsonPath('data.applied', true);

        $bruno = Usuario::query()->where('correo', 'bruno.benitez@ueb.edu.ec')->firstOrFail();
        $this->assertSame($this->old->getKey(), $bruno->malla_id);
        $this->assertSame(0, $ana->inscripciones()->count());
        $this->assertSame(1, $bruno->inscripciones()->count());
    }

    public function test_the_malla_must_belong_to_the_career(): void
    {
        $this->upload(['malla_id' => $this->currentMalla($this->otherCareer)->getKey()])
            ->assertUnprocessable()->assertJsonValidationErrors(['malla_id' => 'La malla no pertenece a la carrera.']);
    }

    public function test_without_a_malla_the_subject_is_searched_first_in_the_current_malla(): void
    {
        $current = $this->createSubject($this->cycle, 'NEW-1', 'Algoritmos');

        $this->upload()->assertOk()->assertJsonPath('data.subject.id', $current->getKey());
        $current->update(['is_active' => false]);
        $this->upload()->assertOk()->assertJsonPath('data.subject.code', 'OLD-1');
    }
}

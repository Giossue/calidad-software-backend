<?php

namespace Tests\Feature\Api\V1\Tutoring;

use App\Imports\RosterPdf;
use App\Models\InscripcionTutoria;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

/**
 * La columna opcional `matricula` del listado indica la matrícula de cada
 * estudiante; vacía es 1.ª y un valor no reconocido es un error de la fila.
 */
class RosterEnrollmentNumberTest extends TutoringTestCase
{
    private const TEXT = "UNIVERSIDAD ESTATAL DE BOLÍVAR\nREGISTRO ACTIVIDADES ACADÉMICAS\n"
        ."Carrera: SOFTWARE\tFecha: ____________\n"
        ."Docente: PEREZ MORA LUIS\tHora: ____________\n"
        ."Asignatura: CALIDAD DE SOFTWARE\tParalelo: A\n"
        ."Nº APELLIDOS Y NOMBRES\tCORREO\tTELÉFONO\tFIRMA\n"
        ."1 Andrade Vera Ana\tana.andrade@ueb.edu.ec 0991112233\n"
        ."2 Benitez Cruz Bruno\tbruno.benitez@ueb.edu.ec 0992223344\n"
        ."3 Cando Rios Carla\tcarla.cando@ueb.edu.ec 0993334455\n"
        ."4 Duran Paz Diego\tdiego.duran@ueb.edu.ec 0994445566\n"
        ."5 Espin Lara Elena\telena.espin@ueb.edu.ec 0995556677\n"
        ."6 Flores Gil Fabian\tfabian.flores@ueb.edu.ec 0996667788\n";

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->createSubject($this->cycle);
        Sanctum::actingAs($this->coordinator, ['*']);
    }

    /** @param array<string, string|null> $matriculas Valor de la columna por correo; sin clave, la fila no trae la columna. */
    private function upload(array $matriculas, bool $confirm = false)
    {
        $this->app->instance(RosterPdf::class, new class(self::TEXT, $matriculas) extends RosterPdf
        {
            public function __construct(private string $text, private array $matriculas) {}

            public function read(string $path): array
            {
                $roster = self::parseText($this->text);
                $roster['students'] = array_map(
                    fn (array $row): array => array_key_exists($row['email'], $this->matriculas) ? [...$row, 'matricula' => $this->matriculas[$row['email']]] : $row,
                    $roster['students'],
                );

                return $roster;
            }
        });

        return $this->post(self::API.'/roster-imports', [
            'file' => UploadedFile::fake()->create('LISTADO.pdf', 20, 'application/pdf'),
            'career_id' => $this->career->getKey(),
            'confirm' => $confirm ? '1' : '0',
        ], ['Accept' => 'application/json']);
    }

    public function test_the_matricula_column_sets_each_enrollment_and_an_invalid_value_is_a_row_error(): void
    {
        $matriculas = [
            'ana.andrade@ueb.edu.ec' => '2',
            'bruno.benitez@ueb.edu.ec' => ' Segunda ',
            'carla.cando@ueb.edu.ec' => '3.ª',
            'diego.duran@ueb.edu.ec' => '',
            'fabian.flores@ueb.edu.ec' => 'cuarta',
        ];

        $preview = $this->upload($matriculas)->assertOk();
        $this->assertSame([2, 2, 3, 1, 1, null], array_column($preview->json('data.students'), 'enrollment_number'));
        $this->assertSame(['new', 'new', 'new', 'new', 'new', 'blocked'], array_column($preview->json('data.students'), 'status'));
        $this->assertSame('La matrícula debe ser 1.ª, 2.ª o 3.ª.', $preview->json('data.students.5.message'));
        $this->assertSame(0, InscripcionTutoria::query()->count());

        $this->upload($matriculas, confirm: true)->assertOk()->assertJsonPath('data.applied', true);

        $numbers = InscripcionTutoria::query()->with('estudiante')->get()->mapWithKeys(fn (InscripcionTutoria $enrollment) => [$enrollment->estudiante->correo => $enrollment->numero_matricula])->sortKeys()->all();
        $this->assertSame([
            'ana.andrade@ueb.edu.ec' => 2,
            'bruno.benitez@ueb.edu.ec' => 2,
            'carla.cando@ueb.edu.ec' => 3,
            'diego.duran@ueb.edu.ec' => 1,
            'elena.espin@ueb.edu.ec' => 1,
        ], $numbers);
    }

    public function test_every_accepted_spelling_is_recognised(): void
    {
        $values = ['1' => 1, '2' => 2, '3' => 3, '1.ª' => 1, '2ª' => 2, '3.ª' => 3, 'PRIMERA' => 1, 'segunda' => 2, 'Tercera' => 3];
        foreach ($values as $value => $expected) {
            $this->upload(['ana.andrade@ueb.edu.ec' => (string) $value])->assertOk()
                ->assertJsonPath('data.students.0.enrollment_number', $expected)
                ->assertJsonPath('data.students.0.status', 'new');
        }
        foreach (['4', '0', '2.5', 'segundo intento'] as $value) {
            $this->upload(['ana.andrade@ueb.edu.ec' => $value])->assertOk()
                ->assertJsonPath('data.students.0.status', 'blocked')
                ->assertJsonPath('data.students.0.message', 'La matrícula debe ser 1.ª, 2.ª o 3.ª.');
        }
    }
}

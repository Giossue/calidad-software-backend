<?php

namespace Tests\Feature\Api\V1\Tutoring;

use App\Imports\RosterPdf;
use App\Models\AsignaturaTutoria;
use App\Models\InscripcionTutoria;
use App\Models\Subject;
use App\Models\Usuario;
use App\Notifications\QueuedProvisionalPasswordNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

class RosterImportTest extends TutoringTestCase
{
    /** Texto como lo extrae el lector del PDF del sistema académico (datos ficticios). */
    private const TEXT = "UNIVERSIDAD ESTATAL DE BOLÍVAR\nREGISTRO ACTIVIDADES ACADÉMICAS\n"
        ."Carrera: GRUPO COMUN\tFecha: ____________\n"
        ."Docente: PEREZ MORA LUIS\nALBERTO\tHora: ____________\n"
        ."Asignatura: CALCULO DIFERENCIAL PARA\nINGENIERIA\tParalelo: A\n"
        ."CAPÍTULO\tCONTENIDO\tOBSERVACIÓN\n"
        ."Nº APELLIDOS Y NOMBRES\tCORREO\tTELÉFONO\tFIRMA\n"
        ."1 Andrade Vera Ana Lucia\tana.andrade@ueb.edu.ec 0991112233\n"
        ."2 Benitez  Cruz Bruno\tbruno.benitez@mailes.ueb.edu.ec\n"
        ."3 Cando Rios Carla Sofia carla.cando-rios@ueb.edu.e\nc\n0993334455\n"
        ."4 Duran Paz Diego\tdiego.duran@ueb.edu.ec0994445566\n"
        ."Página 1/226/08/2026\t09:57\n\n"
        ."5 Espin Lara Elena\telena.espin@ueb.edu.ec 1755091217\n"
        ."6 Flores Gil Fabian\tfabian.flores@gmail.com 0996667788\n";

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->subject = $this->createSubject($this->cycle, 'TI-B1-001');
        $this->subject->update(['name' => 'Cálculo diferencial para ingeniería']);
    }

    private function upload(bool $confirm = false, ?string $text = null)
    {
        $this->app->instance(RosterPdf::class, new class($text ?? self::TEXT) extends RosterPdf
        {
            public function __construct(private string $text) {}

            public function read(string $path): array
            {
                return self::parseText($this->text);
            }
        });

        return $this->post(self::API.'/roster-imports', [
            'file' => UploadedFile::fake()->create('LISTADO.pdf', 20, 'application/pdf'),
            'career_id' => $this->career->getKey(),
            'confirm' => $confirm ? '1' : '0',
        ], ['Accept' => 'application/json']);
    }

    public function test_the_parser_reads_header_wrapped_values_and_every_student(): void
    {
        $roster = RosterPdf::parseText(self::TEXT);

        $this->assertSame(['GRUPO COMUN', 'PEREZ MORA LUIS ALBERTO', 'CALCULO DIFERENCIAL PARA INGENIERIA', 'A'], [$roster['career'], $roster['teacher'], $roster['subject'], $roster['section']]);
        $this->assertSame([
            ['number' => 1, 'name' => 'Andrade Vera Ana Lucia', 'email' => 'ana.andrade@ueb.edu.ec', 'phone' => '0991112233'],
            ['number' => 2, 'name' => 'Benitez Cruz Bruno', 'email' => 'bruno.benitez@mailes.ueb.edu.ec', 'phone' => null],
            ['number' => 3, 'name' => 'Cando Rios Carla Sofia', 'email' => 'carla.cando-rios@ueb.edu.ec', 'phone' => '0993334455'],
            ['number' => 4, 'name' => 'Duran Paz Diego', 'email' => 'diego.duran@ueb.edu.ec', 'phone' => '0994445566'],
            ['number' => 5, 'name' => 'Espin Lara Elena', 'email' => 'elena.espin@ueb.edu.ec', 'phone' => null],
            ['number' => 6, 'name' => 'Flores Gil Fabian', 'email' => 'fabian.flores@gmail.com', 'phone' => '0996667788'],
        ], $roster['students']);

        $this->expectException(ValidationException::class);
        RosterPdf::parseText("Un PDF cualquiera\nsin cabecera\n");
    }

    public function test_preview_describes_the_load_without_changing_anything(): void
    {
        Sanctum::actingAs($this->coordinator, ['*']);
        $teacher = Usuario::factory()->withRole('docente')->create(['nombre' => 'Pérez Mora Luis Alberto']);
        $existing = Usuario::factory()->withRole('estudiante')->create(['correo' => 'ana.andrade@ueb.edu.ec', 'fk_carrera' => $this->career->getKey()]);
        Usuario::factory()->withRole('estudiante')->create(['correo' => 'diego.duran@ueb.edu.ec', 'fk_carrera' => $this->otherCareer->getKey()]);

        $response = $this->upload()->assertOk()
            ->assertJsonPath('data.applied', false)
            ->assertJsonPath('data.subject.id', $this->subject->id)
            ->assertJsonPath('data.cycle_number', 1)
            ->assertJsonPath('data.period_name', 'PAO 2026')
            ->assertJsonPath('data.teacher.id', $teacher->getKey())
            ->assertJsonPath('data.tutoring.exists', false)
            ->assertJsonPath('data.errors', []);

        $this->assertSame(['enroll', 'new', 'new', 'blocked', 'new', 'blocked'], array_column($response->json('data.students'), 'status'));
        $this->assertSame('El estudiante está registrado en otra carrera.', $response->json('data.students.3.message'));
        $this->assertSame(0, AsignaturaTutoria::query()->count());
        $this->assertSame(1, Usuario::query()->whereHas('roles', fn ($q) => $q->where('slug', 'estudiante'))->where('fk_carrera', $this->career->getKey())->count());
        $this->assertNull($existing->refresh()->fk_ciclo);
    }

    public function test_confirm_creates_the_tutoring_with_its_teacher_accounts_and_enrollments(): void
    {
        Sanctum::actingAs($this->coordinator, ['*']);
        $teacher = Usuario::factory()->withRole('docente')->create(['nombre' => 'Perez Mora Luis Alberto']);
        $existing = Usuario::factory()->withRole('estudiante')->create(['correo' => 'ana.andrade@ueb.edu.ec', 'fk_carrera' => $this->career->getKey()]);

        $response = $this->upload(confirm: true)->assertOk()->assertJsonPath('data.applied', true);

        $tutoring = AsignaturaTutoria::query()->findOrFail($response->json('data.tutoring.id'));
        $this->assertSame([$this->subject->id, $teacher->getKey(), $this->cycle->fk_paralelo, $this->period->getKey()], [$tutoring->subject_id, $tutoring->fk_docente, $tutoring->fk_paralelo, $tutoring->fk_periodo]);
        $enrolled = InscripcionTutoria::query()->where('fk_asig_tutoria', $tutoring->getKey())->where('estado', true)->with('estudiante')->get()->pluck('estudiante.correo')->sort()->values()->all();
        $this->assertSame(['ana.andrade@ueb.edu.ec', 'bruno.benitez@mailes.ueb.edu.ec', 'carla.cando-rios@ueb.edu.ec', 'diego.duran@ueb.edu.ec', 'elena.espin@ueb.edu.ec'], $enrolled);

        $new = Usuario::query()->where('correo', 'carla.cando-rios@ueb.edu.ec')->firstOrFail();
        $this->assertSame(['Cando Rios Carla Sofia', '0993334455', $this->cycle->getKey(), $this->career->getKey()], [$new->nombre, $new->telefono, $new->fk_ciclo, $new->fk_carrera]);
        $this->assertTrue($new->hasRole('estudiante') && $new->must_complete_profile);
        $this->assertSame($this->cycle->getKey(), $existing->refresh()->fk_ciclo);
        Notification::assertSentTo($new, QueuedProvisionalPasswordNotification::class);
        Notification::assertNotSentTo($existing, QueuedProvisionalPasswordNotification::class);

        // Volver a subir el mismo listado no duplica nada.
        $again = $this->upload(confirm: true)->assertOk();
        $this->assertSame([$tutoring->getKey()], AsignaturaTutoria::query()->pluck('id_asig_tutoria')->all());
        $this->assertSame(['enrolled', 'enrolled', 'enrolled', 'enrolled', 'enrolled', 'blocked'], array_column($again->json('data.students'), 'status'));
    }

    public function test_it_reports_what_is_missing_and_respects_pending_subjects(): void
    {
        Sanctum::actingAs($this->coordinator, ['*']);
        $this->upload(true, str_replace('CALCULO DIFERENCIAL PARA', 'FISICA', self::TEXT))->assertOk()
            ->assertJsonPath('data.applied', false)
            ->assertJsonPath('data.errors.0', 'La asignatura «FISICA INGENIERIA» no está registrada en Software. Regístrala en Asignaturas.');
        $this->assertSame(0, Usuario::query()->where('correo', 'like', '%@ueb.edu.ec')->whereHas('roles', fn ($q) => $q->where('slug', 'estudiante'))->count());

        $previous = $this->createSubject($this->cycle, 'TI-B0-001');
        $previous->update(['name' => 'Matemática básica']);
        $previous->nextSubjects()->attach($this->subject);
        $carrier = Usuario::factory()->withRole('estudiante')->create(['correo' => 'ana.andrade@ueb.edu.ec', 'fk_carrera' => $this->career->getKey()]);
        $carrier->pendingSubjects()->attach($previous);

        $this->upload()->assertOk()
            ->assertJsonPath('data.students.0.status', 'blocked')
            ->assertJsonPath('data.students.0.message', 'Arrastra «Matemática básica», previa de esta asignatura.')
            ->assertJsonPath('data.teacher', null)
            ->assertJsonPath('data.notes.0', 'No se encontró al docente «PEREZ MORA LUIS ALBERTO» entre los docentes registrados; asígnalo después en Tutorías.');

        Sanctum::actingAs(Usuario::factory()->withRole('docente')->create(), ['*']);
        $this->upload()->assertForbidden();
    }
}

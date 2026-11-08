<?php

namespace Tests\Feature\Api\V1\Tutoring;

use App\Models\Ciclo;
use App\Models\Subject;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

class PendingSubjectsTest extends TutoringTestCase
{
    private Ciclo $second;

    private Ciclo $third;

    private Subject $programmingOne;

    private Subject $programmingTwo;

    private Subject $networks;

    private Subject $programmingThree;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        // 2.º: Programación I → 3.º: Programación II → 4.º: Programación III; Redes (3.º) no depende de nada.
        $this->second = $this->createCycle($this->career, null, 2);
        $this->third = $this->createCycle($this->career, null, 3);
        $this->programmingOne = $this->subject($this->second, 'TI-B2-006', 'Programación I');
        $this->programmingTwo = $this->subject($this->third, 'TI-B3-011', 'Programación II');
        $this->networks = $this->subject($this->third, 'TI-B3-013', 'Redes');
        $this->programmingThree = $this->subject($this->createCycle($this->career, null, 4), 'TI-P4-016', 'Programación III');
        $this->programmingOne->nextSubjects()->attach($this->programmingTwo);
        $this->programmingTwo->nextSubjects()->attach($this->programmingThree);

        Sanctum::actingAs($this->coordinator, ['*']);
    }

    private function subject(Ciclo $cycle, string $code, string $name): Subject
    {
        $subject = $this->createSubject($cycle, $code);
        $subject->update(['name' => $name]);

        return $subject;
    }

    private function import(string $csv): void
    {
        $this->post('/api/v1/imports/students', ['file' => UploadedFile::fake()->createWithContent('estudiantes.csv', $csv)], ['Accept' => 'application/json'])->assertAccepted();
    }

    public function test_the_import_records_the_pending_subjects_and_validates_them(): void
    {
        $this->import("correo;facultad;carrera;ciclo;asignaturas_pendientes\n"
            ."arrastra@ueb.edu.ec;Ciencias;Software;3;ti-b2-006\n"
            ."al.dia@ueb.edu.ec;Ciencias;Software;3;\n"
            ."no.existe@ueb.edu.ec;Ciencias;Software;3;Física\n"
            ."mismo.ciclo@ueb.edu.ec;Ciencias;Software;3;Redes\n");

        $this->assertSame([$this->programmingOne->id], Usuario::query()->where('correo', 'arrastra@ueb.edu.ec')->firstOrFail()->pendingSubjects()->pluck('subjects.id')->all());
        $this->assertSame([], Usuario::query()->where('correo', 'al.dia@ueb.edu.ec')->firstOrFail()->pendingSubjects()->pluck('subjects.id')->all());
        $listed = collect($this->getJson(self::API.'/students?per_page=50')->assertOk()->json('data'))->firstWhere('email', 'arrastra@ueb.edu.ec');
        $this->assertSame([['id' => $this->programmingOne->id, 'code' => 'TI-B2-006', 'name' => 'Programación I']], $listed['pending_subjects']);
        $this->assertDatabaseMissing('usuario', ['correo' => 'no.existe@ueb.edu.ec']);
        $this->assertDatabaseMissing('usuario', ['correo' => 'mismo.ciclo@ueb.edu.ec']);

        $this->getJson('/api/v1/imports/1')
            ->assertJsonPath('data.created_count', 2)
            ->assertJsonPath('data.errors.0.messages.0', 'La asignatura pendiente «Física» no existe en la carrera indicada.')
            ->assertJsonPath('data.errors.1.messages.0', '«Redes» es de 3.º ciclo: solo se arrastran asignaturas de ciclos anteriores al 3.º.');
    }

    public function test_reimporting_a_registered_student_updates_only_the_pending_subjects(): void
    {
        $this->import("correo;facultad;carrera;ciclo\nya@ueb.edu.ec;Ciencias;Software;3\n");
        $this->import("correo;facultad;carrera;ciclo;asignaturas_pendientes\nya@ueb.edu.ec;Ciencias;Software;3;Programación I\n");

        $this->assertSame([$this->programmingOne->id], Usuario::query()->where('correo', 'ya@ueb.edu.ec')->firstOrFail()->pendingSubjects()->pluck('subjects.id')->all());
        $this->getJson('/api/v1/imports/2')
            ->assertJsonPath('data.created_count', 0)
            ->assertJsonPath('data.skipped_count', 1)
            ->assertJsonPath('data.warnings.0.messages.0', 'El estudiante ya@ueb.edu.ec ya estaba registrado: se actualizaron sus asignaturas pendientes (1).');
    }

    public function test_a_student_with_a_pending_subject_is_not_offered_the_subjects_that_continue_it(): void
    {
        $this->import("correo;facultad;carrera;ciclo;asignaturas_pendientes\narrastra@ueb.edu.ec;Ciencias;Software;3;TI-B2-006\nal.dia@ueb.edu.ec;Ciencias;Software;3;\n");
        $carrier = Usuario::query()->where('correo', 'arrastra@ueb.edu.ec')->firstOrFail();
        $upToDate = Usuario::query()->where('correo', 'al.dia@ueb.edu.ec')->firstOrFail();
        $programming = $this->createTutoring($this->third, $this->programmingTwo);
        $networks = $this->createTutoring($this->third, $this->networks);
        $later = $this->createTutoring($this->third, $this->programmingThree);

        $available = fn ($tutoring) => collect($this->getJson(self::API."/tutorings/{$tutoring->getKey()}/available-students")->assertOk()->json('data'))->pluck('id')->sort()->values()->all();
        $this->assertSame([$upToDate->getKey()], $available($programming));
        $this->assertSame([$upToDate->getKey()], $available($later));
        $this->assertEqualsCanonicalizing([$carrier->getKey(), $upToDate->getKey()], $available($networks));

        $this->postJson(self::API."/tutorings/{$programming->getKey()}/students", ['student_id' => $carrier->getKey()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['student_id' => 'El estudiante arrastra «Programación I», previa de esta asignatura. Podrá inscribirse cuando la apruebe.']);
        $this->postJson(self::API."/tutorings/{$networks->getKey()}/students", ['student_id' => $carrier->getKey()])->assertCreated();

        $offered = collect($this->getJson(self::API."/students/{$carrier->getKey()}/available-tutorings")->assertOk()->json('data'))->pluck('id')->all();
        $this->assertNotContains($programming->getKey(), $offered);
        $this->assertNotContains($later->getKey(), $offered);
        $this->postJson(self::API."/students/{$carrier->getKey()}/enroll", ['tutoring_id' => $later->getKey()])->assertUnprocessable()->assertJsonValidationErrors('tutoring_id');
    }
}

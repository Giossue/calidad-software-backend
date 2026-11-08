<?php

namespace Tests\Feature\Api\V1\Tutoring;

use App\Models\AsignaturaTutoria;
use App\Models\Carrera;
use App\Models\Ciclo;
use App\Models\InscripcionTutoria;
use App\Models\Paralelo;
use App\Models\PeriodoAcademico;
use App\Models\Subject;
use App\Models\Usuario;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

class SubjectSequenceTest extends TutoringTestCase
{
    private Ciclo $secondCycle;

    private PeriodoAcademico $pastPeriod;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->secondCycle = $this->createCycle($this->career, Paralelo::query()->findOrFail($this->cycle->fk_paralelo), 2);
        $this->pastPeriod = PeriodoAcademico::query()->create(['nombre' => 'PAO 2025', 'fecha_inicio' => '2025-11-01', 'fecha_fin' => '2026-03-31', 'estado' => false]);
    }

    private function subject(Ciclo $cycle, string $code, string $name): Subject
    {
        $subject = $this->createSubject($cycle, $code);
        $subject->update(['name' => $name]);

        return $subject;
    }

    /** Tutoría de un período anterior con el estudiante y su resultado final. */
    private function approvedIn(Subject $subject, Usuario $student, string $result = InscripcionTutoria::APPROVED, ?int $sectionId = null): InscripcionTutoria
    {
        $sectionId ??= $this->cycle->fk_paralelo;
        $cycle = Ciclo::query()->firstOrCreate(
            ['fk_carrera' => $this->career->getKey(), 'numero' => 1, 'fk_paralelo' => $sectionId],
            ['nombre' => 'Ciclo 1', 'estado' => true],
        );
        $tutoring = AsignaturaTutoria::query()->firstOrCreate(
            ['subject_id' => $subject->getKey(), 'fk_ciclo' => $cycle->getKey(), 'fk_periodo' => $this->pastPeriod->getKey()],
            ['fk_modalidad' => $this->modality->getKey(), 'fk_paralelo' => $sectionId, 'nombre' => $subject->name, 'estado' => true],
        );

        return InscripcionTutoria::query()->create([
            'fk_asig_tutoria' => $tutoring->getKey(), 'fk_id_usuario' => $student->getKey(),
            'fecha_inscripcion' => '2026-01-10', 'estado' => true, 'final_result' => $result,
        ]);
    }

    public function test_coordinator_configures_the_sequence_and_cycles_are_rejected(): void
    {
        $first = $this->subject($this->cycle, 'C1', 'CALCULO I');
        $algebra = $this->subject($this->cycle, 'AL', 'ALGEBRA LINEAL');
        $second = $this->subject($this->secondCycle, 'C2', 'CALCULO II');
        $foreign = $this->subject($this->otherCycle, 'X1', 'OTRA CARRERA');
        Sanctum::actingAs($this->coordinator, ['*']);

        $this->patchJson(self::API."/subjects/{$first->id}", ['next_subject_ids' => [$second->id]])->assertOk()
            ->assertJsonPath('data.next_subjects.0.name', 'CALCULO II');
        $this->patchJson(self::API."/subjects/{$algebra->id}", ['next_subject_ids' => [$second->id]])->assertOk();
        $this->getJson(self::API."/subjects?career_id={$this->career->id}&cycle_number=2")->assertOk()
            ->assertJsonPath('data.0.previous_subjects.*.name', ['CALCULO I', 'ALGEBRA LINEAL']);

        $this->patchJson(self::API."/subjects/{$second->id}", ['next_subject_ids' => [$first->id]])
            ->assertUnprocessable()->assertJsonValidationErrors(['next_subject_ids' => '«CALCULO I» ya lleva a esta asignatura en la secuencia; no puede continuarla.']);
        $this->patchJson(self::API."/subjects/{$first->id}", ['next_subject_ids' => [$foreign->id]])
            ->assertUnprocessable()->assertJsonValidationErrors(['next_subject_ids.0' => 'La asignatura que continúa debe pertenecer a la misma carrera.']);
        $this->patchJson(self::API."/subjects/{$first->id}", ['next_subject_ids' => [$first->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('next_subject_ids.0');

        // Vaciar la lista quita la secuencia.
        $this->patchJson(self::API."/subjects/{$first->id}", ['next_subject_ids' => []])->assertOk()->assertJsonPath('data.next_subjects', []);
    }

    public function test_creating_the_next_tutoring_enrolls_students_who_approved_every_previous_subject_in_the_same_section(): void
    {
        $first = $this->subject($this->cycle, 'C1', 'CALCULO I');
        $algebra = $this->subject($this->cycle, 'AL', 'ALGEBRA LINEAL');
        $second = $this->subject($this->secondCycle, 'C2', 'CALCULO II');
        $first->nextSubjects()->attach($second);
        $algebra->nextSubjects()->attach($second);

        $ready = Usuario::factory()->student()->create();
        $this->approvedIn($first, $ready);
        $this->approvedIn($algebra, $ready);
        $onlyOne = Usuario::factory()->student()->create();
        $this->approvedIn($first, $onlyOne);
        $failed = Usuario::factory()->student()->create();
        $this->approvedIn($first, $failed);
        $this->approvedIn($algebra, $failed, InscripcionTutoria::FAILED);
        $otherSection = Usuario::factory()->student()->create();
        $sectionB = Paralelo::query()->create(['nombre' => 'B', 'estado' => true]);
        $this->approvedIn($first, $otherSection, sectionId: $sectionB->getKey());
        $this->approvedIn($algebra, $otherSection, sectionId: $sectionB->getKey());

        Sanctum::actingAs($this->coordinator, ['*']);
        $id = $this->postJson(self::API.'/tutorings', $this->tutoringPayload($second, $this->secondCycle))->assertCreated()->json('data.id');

        $enrolled = InscripcionTutoria::query()->where('fk_asig_tutoria', $id)->pluck('fk_id_usuario')->all();
        $this->assertSame([$ready->getKey()], $enrolled);
        $this->assertTrue($ready->paralelos()->whereKey($this->secondCycle->fk_paralelo)->exists());
    }

    public function test_a_coordinator_only_sees_the_curriculum_of_their_own_careers(): void
    {
        $own = $this->subject($this->cycle, 'C1', 'CALCULO I');
        $foreign = $this->subject($this->otherCycle, 'X1', 'CONTABILIDAD I');
        $foreignNext = $this->subject($this->otherCycle, 'X2', 'CONTABILIDAD II');
        $foreign->nextSubjects()->attach($foreignNext);
        Sanctum::actingAs($this->coordinator, ['*']);

        $this->getJson(self::API.'/careers')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->career->getKey());
        $this->getJson(self::API."/subjects?career_id={$this->otherCareer->getKey()}&per_page=100")->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(self::API.'/subjects?per_page=100')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
        $this->patchJson(self::API."/subjects/{$foreign->id}", ['next_subject_ids' => []])->assertForbidden();
        $this->assertSame([$foreignNext->id], $foreign->nextSubjects()->pluck('subjects.id')->all());
    }

    public function test_subjects_without_a_sequence_end_it(): void
    {
        $last = $this->subject($this->cycle, 'TIC', 'TRABAJO DE INTEGRACION');
        $other = $this->subject($this->secondCycle, 'OT', 'OTRA');
        $student = Usuario::factory()->student()->create();
        $this->approvedIn($last, $student);

        Sanctum::actingAs($this->coordinator, ['*']);
        $id = $this->postJson(self::API.'/tutorings', $this->tutoringPayload($other, $this->secondCycle))->assertCreated()->json('data.id');
        $this->assertSame(0, InscripcionTutoria::query()->where('fk_asig_tutoria', $id)->count());
    }

    public function test_information_technology_curriculum_is_loaded_by_code_and_name(): void
    {
        $ti = Carrera::query()->create(['fk_facultad' => $this->career->fk_facultad, 'fk_modalidad' => $this->modality->getKey(), 'nombre' => 'Tecnologías de la Información', 'estado' => true]);
        $make = fn (?string $code, string $name) => Subject::query()->create(['career_id' => $ti->getKey(), 'code' => $code, 'name' => $name, 'is_active' => true]);
        $algorithms = $make('TI-B1-001', 'ALGORITMOS Y FUNDAMENTOS DE PROGRAMACION');
        $poo = $make('TI-B2-006', 'PROGRAMACION ORIENTADA A OBJETOS');
        $algebra = $make('TI-B1-002', 'ALGEBRA LINEAL');
        $calculus = $make('TI-B1-004', 'CALCULO I');
        $calculusTwo = $make('TI-B2-009', 'CALCULO II');
        $practices = $make(null, 'PRACTICAS DE SERVICIO COMUNITARIO');
        $integration = $make(null, 'TRABAJO DE INTEGRACION CURRICULAR');
        // Otra carrera con códigos parecidos no se toca.
        $software = $this->subject($this->cycle, 'TI-B1-001', 'ALGORITMOS');

        (require database_path('migrations/2026_11_08_100200_load_information_technology_subject_sequences.php'))->up();

        $this->assertSame([$poo->id], $algorithms->nextSubjects()->pluck('subjects.id')->all());
        $this->assertEqualsCanonicalizing([$algebra->id, $calculus->id], $calculusTwo->previousSubjects()->pluck('subjects.id')->all());
        $this->assertSame([$integration->id], $practices->nextSubjects()->pluck('subjects.id')->all());
        $this->assertSame([], $software->nextSubjects()->pluck('subjects.id')->all());
    }
}

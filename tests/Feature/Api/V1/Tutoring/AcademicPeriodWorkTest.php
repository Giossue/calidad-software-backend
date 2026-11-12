<?php

namespace Tests\Feature\Api\V1\Tutoring;

use App\Models\AsignaturaTutoria;
use App\Models\Ciclo;
use App\Models\InscripcionTutoria;
use App\Models\Paralelo;
use App\Models\PeriodoAcademico;
use App\Models\Subject;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

/** Trabajo diario por período académico: período vigente, historial y ciclo por período. */
class AcademicPeriodWorkTest extends TutoringTestCase
{
    private const READ_ONLY = 'La tutoría pertenece a un período académico que no está vigente; solo puede consultarse.';

    private PeriodoAcademico $pastPeriod;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));
        Notification::fake();
        $this->pastPeriod = PeriodoAcademico::query()->create([
            'nombre' => 'PAO 2025', 'fecha_inicio' => '2025-05-01', 'fecha_fin' => '2025-10-31', 'estado' => false,
        ]);
    }

    public function test_the_database_rejects_a_second_active_period(): void
    {
        $this->expectException(UniqueConstraintViolationException::class);

        PeriodoAcademico::query()->create([
            'nombre' => 'PAO 2027', 'fecha_inicio' => '2027-01-01', 'fecha_fin' => '2027-06-30', 'estado' => true,
        ]);
    }

    public function test_tutorings_list_shows_the_active_period_by_default_and_filters_by_period(): void
    {
        $current = $this->createTutoring();
        $past = $this->tutoringIn($this->pastPeriod, $this->cycle, $this->createSubject(null, 'CS-900', 'Historia'));

        $this->actingAs($this->coordinator)->getJson(self::API.'/tutorings')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $current->getKey())
            ->assertJsonPath('meta.period', ['id' => $this->period->getKey(), 'name' => 'PAO 2026', 'is_active' => true])
            ->assertJsonPath('meta.total', 1);

        $this->getJson(self::API.'/tutorings?period_id='.$this->pastPeriod->getKey())->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $past->getKey())
            ->assertJsonPath('meta.period', ['id' => $this->pastPeriod->getKey(), 'name' => 'PAO 2025', 'is_active' => false]);
    }

    public function test_tutorings_list_is_empty_without_an_active_period(): void
    {
        $this->createTutoring();
        $this->period->update(['estado' => false]);

        $this->actingAs($this->coordinator)->getJson(self::API.'/tutorings')->assertOk()
            ->assertJsonCount(0, 'data')->assertJsonPath('meta.period', null);
    }

    public function test_tutorings_of_a_past_period_are_read_only(): void
    {
        $tutoring = $this->tutoringIn($this->pastPeriod, $this->cycle, $this->createSubject());
        $student = $this->student($this->cycle);
        $this->actingAs($this->coordinator);

        $this->patchJson(self::API."/tutorings/{$tutoring->getKey()}", ['modality_id' => $this->modality->getKey()])
            ->assertUnprocessable()->assertJsonPath('errors.period_id.0', self::READ_ONLY);
        $this->patchJson(self::API."/tutorings/{$tutoring->getKey()}/deactivate")
            ->assertUnprocessable()->assertJsonPath('errors.period_id.0', self::READ_ONLY);
        $this->postJson(self::API."/tutorings/{$tutoring->getKey()}/students", ['student_id' => $student->getKey()])
            ->assertUnprocessable()->assertJsonPath('errors.period_id.0', self::READ_ONLY);
        $this->postJson(self::API."/students/{$student->getKey()}/enroll", ['tutoring_id' => $tutoring->getKey()])
            ->assertUnprocessable()->assertJsonPath('errors.period_id.0', self::READ_ONLY);
        $tutoring->update(['estado' => false]);
        $this->patchJson(self::API."/tutorings/{$tutoring->getKey()}/activate")->assertUnprocessable()->assertJsonValidationErrors('period_id');

        $this->assertDatabaseMissing('inscripcion_tutoria', ['fk_asig_tutoria' => $tutoring->getKey()]);
    }

    public function test_catalog_periods_include_past_periods_newest_first(): void
    {
        $this->actingAs($this->coordinator)->getJson(self::API.'/periods')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $this->period->getKey())->assertJsonPath('data.0.is_active', true)
            ->assertJsonPath('data.1.id', $this->pastPeriod->getKey())->assertJsonPath('data.1.is_active', false)
            ->assertJsonPath('data.1.name', 'PAO 2025');
    }

    public function test_a_subject_belongs_to_a_cycle_number_without_paralelos(): void
    {
        $this->baseCycles(3);
        $this->actingAs($this->coordinator);

        $created = $this->postJson(self::API.'/subjects', ['career_id' => $this->career->getKey(), 'name' => 'Redes', 'cycle_number' => 2, 'modality_id' => $this->modality->getKey()])
            ->assertCreated()->assertJsonPath('data.cycle_number', 2);
        $id = $created->json('data.id');
        $this->assertDatabaseMissing('subject_cycle', ['subject_id' => $id]);

        $this->patchJson(self::API."/subjects/{$id}", ['cycle_number' => 3])->assertOk()->assertJsonPath('data.cycle_number', 3);
        $this->patchJson(self::API."/subjects/{$id}", ['cycle_number' => 4])->assertUnprocessable()->assertJsonValidationErrors('cycle_number');
        $this->postJson(self::API.'/subjects', ['career_id' => $this->career->getKey(), 'name' => 'Bases', 'cycle_number' => 2, 'parallel_ids' => [$this->cycle->fk_paralelo], 'modality_id' => $this->modality->getKey()])
            ->assertUnprocessable()->assertJsonValidationErrors('parallel_ids');

        $this->getJson(self::API.'/subjects?cycle_number=3')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
    }

    public function test_creating_a_tutoring_picks_a_paralelo_row_of_the_subject_cycle(): void
    {
        $this->baseCycles(3);
        $subject = Subject::query()->create(['modality_id' => $this->modality->getKey(), 'career_id' => $this->career->getKey(), 'name' => 'Redes', 'cycle_number' => 2, 'is_active' => true]);
        $sectionB = Paralelo::query()->create(['nombre' => 'B', 'estado' => true]);
        $cycleTwoB = $this->createCycle($this->career, $sectionB, 2);
        $this->actingAs($this->coordinator);

        $this->postJson(self::API.'/tutorings', $this->tutoringPayload($subject, $this->cycle))
            ->assertUnprocessable()->assertJsonPath('errors.cycle_id.0', 'El paralelo debe ser del ciclo 2, el de la asignatura.');

        $this->postJson(self::API.'/tutorings', $this->tutoringPayload($subject, $cycleTwoB))
            ->assertCreated()->assertJsonPath('data.cycle_id', $cycleTwoB->getKey())->assertJsonPath('data.section_name', 'B');
        $this->assertDatabaseMissing('subject_cycle', ['subject_id' => $subject->getKey()]);
    }

    public function test_coordinator_enrollment_records_the_highest_cycle_of_the_period(): void
    {
        $student = $this->student($this->cycle);
        $cycleTwo = $this->createCycle($this->career, $this->cycle->paralelo, 2);
        $base = $this->baseCycles(2);
        $first = $this->createTutoring();
        $second = $this->createTutoring($cycleTwo, $this->createSubject($cycleTwo, 'CS-201', 'Redes'));
        $this->actingAs($this->coordinator);

        $this->postJson(self::API."/tutorings/{$first->getKey()}/students", ['student_id' => $student->getKey()])->assertCreated();
        $this->assertPeriodCycle($student, $this->period, $this->cycle);

        $this->postJson(self::API."/students/{$student->getKey()}/enroll", ['tutoring_id' => $second->getKey()])->assertCreated();
        $this->assertPeriodCycle($student, $this->period, $cycleTwo);
        $this->assertSame($cycleTwo->getKey(), $student->refresh()->fk_ciclo);
        $this->assertNotContains($student->fk_ciclo, [$base[1]->getKey(), $base[2]->getKey()]);

        $enrollment = InscripcionTutoria::query()->where('fk_asig_tutoria', $second->getKey())->firstOrFail();
        $this->patchJson(self::API."/tutorings/{$second->getKey()}/students/{$enrollment->getKey()}/deactivate")->assertOk();
        $this->assertPeriodCycle($student, $this->period, $this->cycle);
        $this->assertSame($this->cycle->getKey(), $student->refresh()->fk_ciclo);
    }

    public function test_teacher_enrollment_records_the_cycle_of_the_period(): void
    {
        $teacher = Usuario::factory()->withRole('docente')->create();
        $base = $this->baseCycles(1);
        $tutoring = $this->createTutoring();
        $tutoring->update(['fk_docente' => $teacher->getKey()]);
        $student = $this->student(null);
        Sanctum::actingAs($teacher, ['access-api']);

        $this->postJson("/api/v1/teacher/tutorings/{$tutoring->getKey()}/students", ['student_id' => $student->getKey()])->assertCreated();

        $this->assertPeriodCycle($student, $this->period, $this->cycle);
        $this->assertSame($this->cycle->getKey(), $student->refresh()->fk_ciclo);
        $this->assertNotSame($base[1]->getKey(), $student->fk_ciclo);
    }

    public function test_automatic_advancement_records_the_next_cycle_in_the_active_period(): void
    {
        $base = $this->baseCycles(2);
        $cycleTwo = $this->createCycle($this->career, $this->cycle->paralelo, 2);
        $first = $this->createSubject($this->cycle, 'CS-101', 'Programación I');
        $next = Subject::query()->create(['modality_id' => $this->modality->getKey(), 'career_id' => $this->career->getKey(), 'code' => 'CS-201', 'name' => 'Programación II', 'cycle_number' => 2, 'is_active' => true]);
        $first->nextSubjects()->attach($next);
        $student = $this->student($this->cycle);
        $past = $this->tutoringIn($this->pastPeriod, $this->cycle, $first);
        InscripcionTutoria::query()->create(['fk_asig_tutoria' => $past->getKey(), 'fk_id_usuario' => $student->getKey(), 'fecha_inscripcion' => '2025-06-01', 'estado' => true, 'final_result' => InscripcionTutoria::APPROVED]);
        $this->assertPeriodCycle($student, $this->pastPeriod, $this->cycle);

        $this->actingAs($this->coordinator)->postJson(self::API.'/tutorings', $this->tutoringPayload($next, $cycleTwo))->assertCreated();

        $this->assertPeriodCycle($student, $this->period, $cycleTwo);
        $this->assertSame($cycleTwo->getKey(), $student->refresh()->fk_ciclo);
        $this->assertNotSame($base[2]->getKey(), $student->fk_ciclo);
        $this->assertPeriodCycle($student, $this->pastPeriod, $this->cycle);
    }

    public function test_enrolling_in_a_higher_cycle_keeps_the_student_section(): void
    {
        $this->baseCycles(2);
        $cycleTwo = $this->createCycle($this->career, $this->cycle->paralelo, 2);
        $student = $this->student($this->cycle);
        $tutoring = $this->createTutoring($cycleTwo, $this->createSubject($cycleTwo, 'CS-201', 'Redes'));
        $this->actingAs($this->coordinator);
        $this->getJson(self::API.'/students')->assertOk()
            ->assertJsonPath('data.0.cycle_number', 1)->assertJsonPath('data.0.section_name', 'A');

        $this->postJson(self::API."/tutorings/{$tutoring->getKey()}/students", ['student_id' => $student->getKey()])->assertCreated();

        $this->getJson(self::API.'/students')->assertOk()
            ->assertJsonPath('data.0.cycle_number', 2)->assertJsonPath('data.0.section_name', 'A');
        $this->assertPeriodCycle($student, $this->period, $cycleTwo);
    }

    public function test_the_period_cycle_prefers_the_paralelo_with_most_enrollments_then_the_lowest_id(): void
    {
        $this->baseCycles(2);
        $cycleTwoA = $this->createCycle($this->career, $this->cycle->paralelo, 2);
        $cycleTwoB = $this->createCycle($this->career, Paralelo::query()->create(['nombre' => 'B', 'estado' => true]), 2);
        $networksA = $this->createTutoring($cycleTwoA, $this->createSubject($cycleTwoA, 'CS-201', 'Redes'));
        $databasesB = $this->createTutoring($cycleTwoB, $this->createSubject($cycleTwoB, 'CS-202', 'Bases'));
        $systemsB = $this->createTutoring($cycleTwoB, $this->createSubject($cycleTwoB, 'CS-203', 'Sistemas'));
        $mostlyB = $this->student($this->cycle);
        $even = $this->student($this->cycle);

        $this->enroll($mostlyB, $networksA);
        $this->enroll($mostlyB, $databasesB);
        $this->enroll($mostlyB, $systemsB);
        $this->enroll($even, $databasesB);
        $this->enroll($even, $networksA);

        $this->assertPeriodCycle($mostlyB, $this->period, $cycleTwoB);
        $this->assertSame($cycleTwoB->getKey(), $mostlyB->refresh()->fk_ciclo);
        $this->assertPeriodCycle($even, $this->period, $cycleTwoA);
        $this->assertSame($cycleTwoA->getKey(), $even->refresh()->fk_ciclo);
    }

    public function test_students_expose_their_cycle_history_newest_first(): void
    {
        $this->baseCycles(2);
        $cycleTwo = $this->createCycle($this->career, $this->cycle->paralelo, 2);
        $student = $this->student($cycleTwo);
        $this->enroll($student, $this->tutoringIn($this->pastPeriod, $this->cycle, $this->createSubject()));
        $this->enroll($student, $this->createTutoring($cycleTwo, $this->createSubject($cycleTwo, 'CS-201', 'Redes')));

        $this->actingAs($this->coordinator)->getJson(self::API.'/students')->assertOk()
            ->assertJsonPath('data.0.cycle_history', [
                ['period_id' => $this->period->getKey(), 'period_name' => 'PAO 2026', 'cycle_number' => 2, 'cycle_name' => $cycleTwo->nombre],
                ['period_id' => $this->pastPeriod->getKey(), 'period_name' => 'PAO 2025', 'cycle_number' => 1, 'cycle_name' => $this->cycle->nombre],
            ]);
    }

    public function test_the_manual_cycle_change_only_assigns_a_first_cycle(): void
    {
        $cycleTwo = $this->createCycle($this->career, $this->cycle->paralelo, 2);
        $withCycle = $this->student($this->cycle);
        $withoutCycle = $this->student(null);
        $this->actingAs($this->coordinator);

        $this->patchJson(self::API."/students/{$withCycle->getKey()}/cycle", ['cycle_id' => $cycleTwo->getKey()])
            ->assertUnprocessable()->assertJsonPath('errors.cycle_id.0', 'El ciclo del estudiante se actualiza solo con sus tutorías del período.');
        $this->assertSame($this->cycle->getKey(), $withCycle->refresh()->fk_ciclo);

        $this->patchJson(self::API."/students/{$withoutCycle->getKey()}/cycle", ['cycle_id' => $cycleTwo->getKey()])
            ->assertOk()->assertJsonPath('data.cycle_number', 2);
    }

    public function test_a_student_cannot_take_the_same_subject_twice_in_a_period(): void
    {
        $subject = $this->createSubject();
        $sectionB = Paralelo::query()->create(['nombre' => 'B', 'estado' => true]);
        $cycleOneB = $this->createCycle($this->career, $sectionB, 1);
        $sectionA = $this->createTutoring($this->cycle, $subject);
        $sectionBTutoring = $this->createTutoring($cycleOneB, $subject);
        $student = $this->student($this->cycle);
        $student->paralelos()->syncWithoutDetaching([$sectionB->getKey() => ['fecha_asignacion' => today(), 'estado' => true]]);
        $this->actingAs($this->coordinator);

        $this->postJson(self::API."/tutorings/{$sectionA->getKey()}/students", ['student_id' => $student->getKey()])->assertCreated();
        $this->postJson(self::API."/tutorings/{$sectionBTutoring->getKey()}/students", ['student_id' => $student->getKey()])
            ->assertUnprocessable()->assertJsonPath('errors.student_id.0', 'El estudiante ya está inscrito en «Calidad de software» en otro paralelo de este período.');
        $this->postJson(self::API."/students/{$student->getKey()}/enroll", ['tutoring_id' => $sectionBTutoring->getKey()])
            ->assertUnprocessable()->assertJsonValidationErrors('tutoring_id');
    }

    /** @return array<int, Ciclo> ciclos base (sin paralelo) 1..$count de la carrera, por número */
    private function baseCycles(int $count): array
    {
        $cycles = [];
        for ($number = 1; $number <= $count; $number++) {
            $cycles[$number] = Ciclo::query()->create(['fk_carrera' => $this->career->getKey(), 'fk_paralelo' => null, 'nombre' => "Ciclo base {$number}", 'numero' => $number, 'estado' => true]);
        }

        return $cycles;
    }

    private function tutoringIn(PeriodoAcademico $period, Ciclo $cycle, Subject $subject): AsignaturaTutoria
    {
        return AsignaturaTutoria::query()->create([
            'subject_id' => $subject->getKey(), 'fk_ciclo' => $cycle->getKey(), 'fk_periodo' => $period->getKey(),
            'fk_modalidad' => $this->modality->getKey(), 'fk_paralelo' => $cycle->fk_paralelo, 'nombre' => $subject->name, 'estado' => true,
        ]);
    }

    private function student(?Ciclo $cycle): Usuario
    {
        $student = Usuario::factory()->withRole('estudiante')->create(['fk_carrera' => $this->career->getKey(), 'fk_ciclo' => $cycle?->getKey()]);
        $student->paralelos()->attach($this->cycle->fk_paralelo, ['fecha_asignacion' => today(), 'estado' => true]);

        return $student;
    }

    private function enroll(Usuario $student, AsignaturaTutoria $tutoring): void
    {
        InscripcionTutoria::query()->create(['fk_asig_tutoria' => $tutoring->getKey(), 'fk_id_usuario' => $student->getKey(), 'fecha_inscripcion' => today(), 'estado' => true]);
    }

    private function assertPeriodCycle(Usuario $student, PeriodoAcademico $period, Ciclo $cycle): void
    {
        $this->assertSame($cycle->getKey(), DB::table('estudiante_periodo')->where('fk_usuario', $student->getKey())->where('fk_periodo', $period->getKey())->value('fk_ciclo'));
    }
}

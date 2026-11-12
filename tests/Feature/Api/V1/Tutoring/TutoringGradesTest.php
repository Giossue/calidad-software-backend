<?php

namespace Tests\Feature\Api\V1\Tutoring;

use App\Models\AsignaturaTutoria;
use App\Models\InscripcionTutoria;
use App\Models\Nota;
use App\Models\PeriodoAcademico;
use App\Models\Usuario;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

class TutoringGradesTest extends TutoringTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function enroll(AsignaturaTutoria $tutoring, string $name, bool $active = true, ?string $result = null): InscripcionTutoria
    {
        $student = Usuario::factory()->withRole('estudiante')->create(['nombre' => $name]);

        return InscripcionTutoria::query()->create([
            'fk_asig_tutoria' => $tutoring->getKey(), 'fk_id_usuario' => $student->getKey(),
            'fecha_inscripcion' => '2026-06-01', 'estado' => $active, 'final_result' => $result,
        ]);
    }

    private function grade(InscripcionTutoria $enrollment, string $type, float $value): void
    {
        Nota::query()->create(['fk_inscripcion' => $enrollment->getKey(), 'tipo' => $type, 'valor' => $value, 'fecha_registro' => today()]);
    }

    private function url(AsignaturaTutoria $tutoring): string
    {
        return self::API.'/tutorings/'.$tutoring->getKey().'/grades';
    }

    public function test_the_career_coordinator_reads_the_grades_of_each_enrolled_student_per_stage(): void
    {
        $tutoring = $this->createTutoring();
        $ana = $this->enroll($tutoring, 'Ana Torres', result: InscripcionTutoria::APPROVED);
        $this->grade($ana, 'diagnostic', 8.5);
        $this->grade($ana, 'partial', 9);
        $this->grade($ana, 'partial_two', 7);
        $beto = $this->enroll($tutoring, 'Beto Ruiz');
        $this->grade($beto, 'partial', 6);
        $this->enroll($tutoring, 'Carla Deshabilitada', active: false);
        Sanctum::actingAs($this->coordinator, ['*']);

        $this->getJson($this->url($tutoring))->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.enrollment_id', $ana->getKey())
            ->assertJsonPath('data.0.name', 'Ana Torres')
            ->assertJsonPath('data.0.final_result', 'approved')
            ->assertJsonPath('data.0.grades', [
                ['stage' => 'diagnostic', 'label' => 'Diagnóstico', 'value' => '8.50'],
                ['stage' => 'partial', 'label' => 'Parcial 1', 'value' => '9.00'],
                ['stage' => 'partial_two', 'label' => 'Parcial 2', 'value' => '7.00'],
            ])
            ->assertJsonPath('data.1.name', 'Beto Ruiz')
            ->assertJsonPath('data.1.final_result', null)
            ->assertJsonPath('data.1.grades.0.value', null)
            ->assertJsonPath('data.1.grades.1.value', '6.00')
            ->assertJsonPath('data.1.grades.2.value', null);
    }

    public function test_a_coordinator_of_another_career_cannot_read_the_grades(): void
    {
        $tutoring = $this->createTutoring($this->otherCycle);
        Sanctum::actingAs($this->coordinator, ['*']);

        $this->getJson($this->url($tutoring))->assertForbidden();
    }

    public function test_a_tutoring_without_grades_lists_its_students_with_empty_grades(): void
    {
        $tutoring = $this->createTutoring();
        $this->enroll($tutoring, 'Ana Torres');
        Sanctum::actingAs($this->coordinator, ['*']);

        $this->getJson($this->url($tutoring))->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.grades', [
                ['stage' => 'diagnostic', 'label' => 'Diagnóstico', 'value' => null],
                ['stage' => 'partial', 'label' => 'Parcial 1', 'value' => null],
                ['stage' => 'partial_two', 'label' => 'Parcial 2', 'value' => null],
            ]);

        $this->getJson($this->url($this->createTutoring(subject: $this->createSubject(code: 'CS-102'))))
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_grades_of_a_past_period_tutoring_stay_readable(): void
    {
        $past = PeriodoAcademico::query()->create([
            'nombre' => 'PAO 2025', 'fecha_inicio' => '2025-05-01', 'fecha_fin' => '2025-10-31', 'estado' => false,
        ]);
        $tutoring = $this->createTutoring();
        $tutoring->update(['fk_periodo' => $past->getKey()]);
        $enrollment = $this->enroll($tutoring, 'Ana Torres', result: InscripcionTutoria::FAILED);
        $this->grade($enrollment, 'partial', 4);
        Sanctum::actingAs($this->coordinator, ['*']);

        $this->getJson($this->url($tutoring))->assertOk()
            ->assertJsonPath('data.0.final_result', 'failed')
            ->assertJsonPath('data.0.grades.1.value', '4.00');
    }
}

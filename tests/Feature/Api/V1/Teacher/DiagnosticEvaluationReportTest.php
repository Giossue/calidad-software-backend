<?php

namespace Tests\Feature\Api\V1\Teacher;

use App\Models\InscripcionTutoria;
use App\Models\Reporte;
use App\Models\Usuario;
use Laravel\Sanctum\Sanctum;

class DiagnosticEvaluationReportTest extends TeacherTestCase
{
    /**
     * Las 14 calificaciones del ejemplo oficial "1.a Calificaciones Diagnóstica" (null = No asiste).
     *
     * @var list<float|null>
     */
    private const OFFICIAL_EXAMPLE = [3, 5.75, 4.5, 1.25, 9.75, null, null, 9.15, 6.5, 1.5, 4, 1.75, 5.5, 7.25];

    public function test_the_report_follows_the_official_format_and_ranges(): void
    {
        $this->enrollWithGrades(self::OFFICIAL_EXAMPLE);

        $summary = $this->getJson($this->path('/reports/diagnostic/preview?date=2026-09-15'))->assertOk()->json('data');

        $this->assertSame('diagnostic_evaluation', $summary['format']);
        $this->assertSame('Ciencias', $summary['faculty']);
        $this->assertSame('Software', $summary['career']);
        $this->assertSame('2026-09-15', $summary['date']);
        $this->assertSame($this->teacher->nombre, $summary['teacher']);
        $this->assertSame(1, $summary['cycle']);
        $this->assertSame('PAO 2026', $summary['academic_period']);
        $this->assertSame('A', $summary['section']);
        $this->assertSame($this->coordinator->nombre, $summary['coordinator']);
        $this->assertSame(14, $summary['students_listed']);
        $this->assertSame(12, $summary['students_evaluated']);

        // Bajo 0%-50% (≤ 5), Medio 51%-69%, Alto 70%-100%; porcentajes sobre los evaluados.
        $groups = collect($summary['groups'])->keyBy('label');
        $this->assertSame([6, 3, 3], [$groups['Bajo']['count'], $groups['Medio']['count'], $groups['Alto']['count']]);
        $this->assertEquals([50, 25, 25], [$groups['Bajo']['percentage'], $groups['Medio']['percentage'], $groups['Alto']['percentage']]);
        $this->assertSame('0% - 50%', $groups['Bajo']['range_label']);
        $this->assertSame(
            ['Semana de adaptación de Conocimientos', 'Tutorías adicionales obligatorias', 'Estudio Autónomo'],
            $groups['Bajo']['action_plan'],
        );
        $this->assertSame(['Semana de adaptación de Conocimientos'], $groups['Alto']['action_plan']);

        $this->assertCount(14, $summary['grades']);
        $this->assertSame(2, collect($summary['grades'])->whereNull('grade')->count());
        $this->assertSame(range(1, 14), array_column($summary['grades'], 'number'));
        $this->assertDatabaseCount('reporte', 0);
    }

    public function test_the_teacher_sends_the_report_and_the_coordinator_reads_it(): void
    {
        $this->enrollWithGrades([3, 8, null]);
        $payload = ['date' => '2026-09-15', 'observations' => 'Refuerzo de derivadas e integrales.'];

        $id = $this->postJson($this->path('/reports/diagnostic'), $payload)->assertCreated()
            ->assertJsonPath('data.type', 'Reporte de la evaluación diagnóstica')
            ->assertJsonPath('data.summary.format', 'diagnostic_evaluation')
            ->assertJsonPath('data.summary.students_listed', 3)
            ->assertJsonPath('data.summary.students_evaluated', 2)
            ->assertJsonPath('data.summary.observations', 'Refuerzo de derivadas e integrales.')
            ->json('data.id');

        // Reenviar sin cambios no duplica el reporte.
        $this->postJson($this->path('/reports/diagnostic'), $payload)->assertCreated()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('reporte', 1);

        $content = Reporte::query()->findOrFail($id)->content;
        $this->assertStringContainsString('REPORTE DE LA EVALUACIÓN DIAGNÓSTICA', $content);
        $this->assertStringContainsString('Bajo (0% - 50%): 1 estudiantes, 50%.', $content);
        $this->assertStringContainsString('No asiste', $content);

        Sanctum::actingAs($this->coordinator, ['access-api']);
        $this->getJson(self::API.'/tutorings/'.$this->tutoring->getKey().'/reports')->assertOk()
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.summary.groups.0.count', 1);
    }

    public function test_inactive_enrollments_are_not_part_of_the_list(): void
    {
        [$active, $inactive] = $this->enrollWithGrades([8, 2]);
        $inactive->update(['estado' => false]);

        $this->getJson($this->path('/reports/diagnostic/preview'))->assertOk()
            ->assertJsonPath('data.students_listed', 1)
            ->assertJsonPath('data.students_evaluated', 1)
            ->assertJsonPath('data.grades.0.name', $active->estudiante->nombre)
            ->assertJsonPath('data.date', today()->toDateString());
    }

    public function test_the_report_requires_diagnostic_grades_and_a_valid_date(): void
    {
        $this->enrollWithGrades([null]);

        $this->postJson($this->path('/reports/diagnostic'), ['date' => '2026-09-15'])->assertUnprocessable()->assertJsonValidationErrors('report');
        $this->postJson($this->path('/reports/diagnostic'), ['date' => '2026-10-15'])->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->postJson($this->path('/reports/diagnostic'), [])->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->assertDatabaseCount('reporte', 0);
    }

    public function test_another_teacher_cannot_preview_or_send_the_report(): void
    {
        $this->enrollWithGrades([8]);
        Sanctum::actingAs(Usuario::factory()->withRole('docente')->create(), ['access-api']);

        $this->getJson($this->path('/reports/diagnostic/preview'))->assertForbidden();
        $this->postJson($this->path('/reports/diagnostic'), ['date' => '2026-09-15'])->assertForbidden();
    }

    /**
     * @param  list<float|int|null>  $grades
     * @return list<InscripcionTutoria>
     */
    private function enrollWithGrades(array $grades): array
    {
        return array_map(function (float|int|null $grade): InscripcionTutoria {
            $enrollment = $this->enrollment();
            if ($grade !== null) {
                $this->putJson($this->path('/students/'.$enrollment->getKey().'/grades/diagnostic'), ['value' => $grade])->assertOk();
            }

            return $enrollment;
        }, $grades);
    }
}

<?php

namespace Tests\Feature\Api\V1\Teacher;

use App\Models\InscripcionTutoria;
use App\Models\Reporte;
use App\Models\Usuario;
use Laravel\Sanctum\Sanctum;

class DiagnosticGradesReportTest extends TeacherTestCase
{
    public function test_the_teacher_previews_and_sends_the_grades_and_the_coordinator_reads_them(): void
    {
        $this->enrollWithGrades([5.75, null, 9]);

        $summary = $this->getJson($this->path('/reports/diagnostic-grades/preview'))->assertOk()->json('data');
        $this->assertSame('diagnostic_grades', $summary['format']);
        $this->assertSame('Software', $summary['career']);
        $this->assertSame('PAO 2026', $summary['academic_period']);
        $this->assertSame($this->teacher->nombre, $summary['teacher']);
        $this->assertSame('A', $summary['section']);
        $this->assertSame([1, 2, 3], array_column($summary['grades'], 'number'));
        $this->assertSame(1, collect($summary['grades'])->whereNull('grade')->count());
        $this->assertArrayNotHasKey('group', $summary['grades'][0]);
        $this->assertDatabaseCount('reporte', 0);

        $id = $this->postJson($this->path('/reports/diagnostic-grades'))->assertCreated()
            ->assertJsonPath('data.type', 'Calificaciones de la evaluación diagnóstica')
            ->assertJsonPath('data.summary.format', 'diagnostic_grades')
            ->json('data.id');

        // Reenviar sin cambios no duplica el reporte.
        $this->postJson($this->path('/reports/diagnostic-grades'))->assertCreated()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('reporte', 1);

        $content = Reporte::query()->findOrFail($id)->content;
        $this->assertStringContainsString('CALIFICACIONES EVALUACIÓN DIAGNÓSTICA', $content);
        $this->assertStringContainsString('No asiste', $content);
        $this->assertStringContainsString('5,75', $content);

        Sanctum::actingAs($this->coordinator, ['access-api']);
        $this->getJson(self::API.'/tutorings/'.$this->tutoring->getKey().'/reports')->assertOk()
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.summary.format', 'diagnostic_grades');
    }

    public function test_the_grades_require_at_least_one_diagnostic_grade(): void
    {
        $this->enrollWithGrades([null]);

        $this->postJson($this->path('/reports/diagnostic-grades'))->assertUnprocessable()->assertJsonValidationErrors('report');
        $this->assertDatabaseCount('reporte', 0);
    }

    public function test_another_teacher_cannot_preview_or_send_the_grades(): void
    {
        $this->enrollWithGrades([8]);
        Sanctum::actingAs(Usuario::factory()->withRole('docente')->create(), ['access-api']);

        $this->getJson($this->path('/reports/diagnostic-grades/preview'))->assertForbidden();
        $this->postJson($this->path('/reports/diagnostic-grades'))->assertForbidden();
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

<?php

namespace Tests\Feature\Api\V1\Teacher;

use App\Models\Actividad;
use App\Models\Asistencia;
use App\Models\InscripcionTutoria;
use App\Models\Metodologia;
use App\Models\Reporte;
use App\Models\Tema;
use App\Models\TutoringSession;
use App\Models\Usuario;
use Laravel\Sanctum\Sanctum;

class TutoringFollowUpReportTest extends TeacherTestCase
{
    private const RANGE = ['partial' => 1, 'from' => '2026-09-01', 'to' => '2026-09-29', 'date' => '2026-09-29'];

    public function test_the_report_follows_the_official_format_for_the_first_partial(): void
    {
        $this->scenario();

        $summary = $this->getJson($this->path('/reports/follow-up/preview?'.http_build_query(self::RANGE)))->assertOk()->json('data');

        $this->assertSame('tutoring_follow_up', $summary['format']);
        $this->assertSame(['Ciencias', 'Software', 'PAO 2026', $this->teacher->nombre, '2026-09-29'], [
            $summary['faculty'], $summary['career'], $summary['academic_period'], $summary['teacher'], $summary['date'],
        ]);
        $this->assertSame('primer parcial', $summary['partial_label']);
        $this->assertStringContainsString('en la CARRERA DE SOFTWARE como apoyo pedagógico', $summary['background']);
        $this->assertSame($this->coordinator->nombre, $summary['coordinator']);

        // Solo las tutorías dentro del rango; una fila por actividad activa y una para el tema sin actividades.
        $this->assertSame(1, $summary['session_count']);
        $this->assertSame([
            ['date' => '2026-09-20', 'topic' => 'Regla del Trapecio', 'activity' => 'Resolución de ejercicios', 'methodology' => 'Aprendizaje basado en problemas (ABP)'],
            ['date' => '2026-09-20', 'topic' => 'Integración de Romberg', 'activity' => null, 'methodology' => null],
        ], $summary['activities']);

        // Riesgo académico: diagnóstico Bajo o Medio; asisten quienes tienen asistencia en el rango.
        $this->assertSame(
            ['at_risk_total' => 3, 'at_risk_attending' => 2, 'not_at_risk_attending' => 1, 'unclassified_attending' => 1],
            $summary['attendance'],
        );

        // Mejoran quienes superan su nota diagnóstica en el parcial; la inscripción inactiva no cuenta.
        $this->assertSame([1, 2, 3], array_column($summary['improved'], 'number'));
        $this->assertEqualsCanonicalizing([[3, 6], [4.5, 6.45], [9, 9.5]], array_map(fn (array $row) => [$row['diagnostic'], $row['partial']], $summary['improved']));
        $this->assertDatabaseCount('reporte', 0);
    }

    public function test_the_second_partial_compares_the_diagnostic_with_the_second_partial_grade(): void
    {
        [$student] = $this->scenario();
        $this->putJson($this->path('/students/'.$student->getKey().'/grades/partial_two'), ['value' => 2])->assertOk();

        $this->getJson($this->path('/reports/follow-up/preview?'.http_build_query([...self::RANGE, 'partial' => 2])))->assertOk()
            ->assertJsonPath('data.partial_label', 'segundo parcial')
            ->assertJsonCount(0, 'data.improved');
    }

    public function test_the_teacher_sends_the_report_and_the_coordinator_reads_it(): void
    {
        $this->scenario();
        $payload = [...self::RANGE, 'observations' => 'Los temas de refuerzo fueron seleccionados por los estudiantes.'];

        $id = $this->postJson($this->path('/reports/follow-up'), $payload)->assertCreated()
            ->assertJsonPath('data.type', 'Reporte de seguimiento de tutorías (primer parcial)')
            ->assertJsonPath('data.summary.format', 'tutoring_follow_up')
            ->assertJsonPath('data.summary.observations', 'Los temas de refuerzo fueron seleccionados por los estudiantes.')
            ->json('data.id');
        $this->postJson($this->path('/reports/follow-up'), $payload)->assertCreated()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('reporte', 1);

        $content = Reporte::query()->findOrFail($id)->content;
        $this->assertStringContainsString('Fecha de presentación: 29 de septiembre del 2026', $content);
        $this->assertStringContainsString('2026/09/20 | Regla del Trapecio | Resolución de ejercicios', $content);

        Sanctum::actingAs($this->coordinator, ['access-api']);
        $this->getJson(self::API.'/tutorings/'.$this->tutoring->getKey().'/reports')->assertOk()
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.summary.attendance.at_risk_total', 3);
    }

    public function test_the_report_validates_the_range_and_requires_sessions(): void
    {
        $this->enrollment();

        $this->postJson($this->path('/reports/follow-up'), self::RANGE)->assertUnprocessable()->assertJsonValidationErrors('report');
        $this->postJson($this->path('/reports/follow-up'), [...self::RANGE, 'from' => '2026-09-30'])->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->postJson($this->path('/reports/follow-up'), [...self::RANGE, 'to' => '2026-10-01'])->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->postJson($this->path('/reports/follow-up'), [...self::RANGE, 'partial' => 3])->assertUnprocessable()->assertJsonValidationErrors('partial');
        $this->getJson($this->path('/reports/follow-up/preview'))->assertUnprocessable()->assertJsonValidationErrors(['partial', 'from', 'to', 'date']);
        $this->assertDatabaseCount('reporte', 0);
    }

    public function test_another_teacher_cannot_preview_or_send_the_report(): void
    {
        $this->scenario();
        Sanctum::actingAs(Usuario::factory()->withRole('docente')->create(), ['access-api']);

        $this->getJson($this->path('/reports/follow-up/preview?'.http_build_query(self::RANGE)))->assertForbidden();
        $this->postJson($this->path('/reports/follow-up'), self::RANGE)->assertForbidden();
    }

    /**
     * Seis inscritos: riesgo que asiste (2), riesgo que solo asistió fuera del rango,
     * sin riesgo que asiste, sin diagnóstico que asiste e inscripción inactiva.
     *
     * @return list<InscripcionTutoria>
     */
    private function scenario(): array
    {
        $inRange = TutoringSession::query()->create(['tutoring_id' => $this->tutoring->getKey(), 'recorded_by' => $this->teacher->getKey(), 'date' => '2026-09-20', 'topics_covered' => true]);
        $outOfRange = TutoringSession::query()->create(['tutoring_id' => $this->tutoring->getKey(), 'recorded_by' => $this->teacher->getKey(), 'date' => '2026-08-25', 'topics_covered' => true]);

        $trapezoid = Tema::query()->create(['fk_asig_tutoria' => $this->tutoring->getKey(), 'nombre' => 'Regla del Trapecio', 'visto' => true, 'estado' => true]);
        $romberg = Tema::query()->create(['fk_asig_tutoria' => $this->tutoring->getKey(), 'nombre' => 'Integración de Romberg', 'visto' => true, 'estado' => true]);
        $exercises = Actividad::query()->create(['fk_tema' => $trapezoid->getKey(), 'nombre' => 'Resolución de ejercicios', 'duracion' => '2 horas', 'estado' => true]);
        Actividad::query()->create(['fk_tema' => $trapezoid->getKey(), 'nombre' => 'Actividad retirada', 'duracion' => '1 hora', 'estado' => false]);
        Metodologia::query()->create(['fk_actividad' => $exercises->getKey(), 'descripcion' => 'Aprendizaje basado en problemas (ABP)', 'estado' => true]);
        Metodologia::query()->create(['fk_actividad' => $exercises->getKey(), 'descripcion' => 'Metodología retirada', 'estado' => false]);
        $inRange->topics()->attach([$trapezoid->getKey(), $romberg->getKey()]);
        $outOfRange->topics()->attach($romberg->getKey());

        $students = [];
        foreach ([[3, 6, $inRange], [4.5, 6.45, $inRange], [6.5, 6, $outOfRange], [9, 9.5, $inRange], [null, 7, $inRange], [2, 8, $inRange]] as [$diagnostic, $partial, $session]) {
            $enrollment = $this->enrollment();
            foreach (['diagnostic' => $diagnostic, 'partial' => $partial] as $type => $value) {
                if ($value !== null) {
                    $this->putJson($this->path('/students/'.$enrollment->getKey().'/grades/'.$type), ['value' => $value])->assertOk();
                }
            }
            Asistencia::query()->create(['fk_inscripcion' => $enrollment->getKey(), 'fk_id_usuario' => $enrollment->fk_id_usuario, 'fecha' => $session->date, 'estado_asistencia' => true, 'session_id' => $session->getKey()]);
            $students[] = $enrollment;
        }
        $students[5]->update(['estado' => false]);

        return $students;
    }
}

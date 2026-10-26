<?php

namespace Tests\Feature\Api\V1\Teacher;

use App\Models\AsignaturaTutoria;
use App\Models\Asistencia;
use App\Models\Horario;
use App\Models\Reporte;
use App\Models\Tema;
use App\Models\TutoringSession;
use App\Models\Usuario;
use Laravel\Sanctum\Sanctum;

class FinalPaoReportTest extends TeacherTestCase
{
    private AsignaturaTutoria $second;

    private AsignaturaTutoria $foreign;

    protected function setUp(): void
    {
        parent::setUp();
        $this->second = $this->createTutoring($this->cycle, $this->createSubject($this->cycle, 'CS-102'));
        $this->second->update(['fk_docente' => $this->teacher->getKey(), 'nombre' => 'Métodos Numéricos']);
        $this->foreign = $this->createTutoring($this->otherCycle, $this->createSubject($this->otherCycle, 'AD-101'));
        $this->foreign->update(['fk_docente' => $this->teacher->getKey()]);
    }

    public function test_the_report_follows_the_official_format_and_the_agreed_rules(): void
    {
        $this->scenario();

        $summary = $this->getJson($this->path('/reports/final-pao/preview?date=2026-09-29'))->assertOk()->json('data');

        $this->assertSame('final_pao', $summary['format']);
        $this->assertSame('Informe de ejecución de tutorías académicas PAO PAO 2026', $summary['subject_line']);
        $this->assertSame('01-TA-2026', $summary['report_number']);
        $this->assertSame(['name' => $this->teacher->nombre, 'email' => $this->teacher->correo, 'extension' => null, 'position' => 'Docente'], $summary['teacher']);
        $this->assertSame($this->coordinator->nombre, $summary['coordinator']['name']);
        $this->assertStringContainsString('asignaturas de Calidad de software y Métodos Numéricos de la carrera de Software', $summary['objective']);
        $this->assertStringContainsString('En la carrera de Software, este acompañamiento', $summary['background']);

        // Solo tutorías propias del mismo período y carrera.
        $this->assertSame([$this->tutoring->getKey(), $this->second->getKey()], array_column($summary['available_tutorings'], 'id'));
        $this->assertSame(['Calidad de software', 'Métodos Numéricos'], array_column($summary['matrix'], 'subject'));

        $this->assertSame(
            [['subject' => 'Calidad de software', 'section' => 'A', 'day' => 'lunes', 'start' => '08:00', 'end' => '10:00', 'room' => 'Lab 1'], ['subject' => 'Calidad de software', 'section' => 'A', 'day' => 'miercoles', 'start' => '14:00', 'end' => '15:00', 'room' => null]],
            $summary['schedule'],
        );

        [$row, $empty] = $summary['matrix'];
        // Lunes 2 h + miércoles 1 h + sábado sin horario (promedio 1,5 h).
        $this->assertEquals(3, $row['weekly_hours']);
        $this->assertEquals(4.5, $row['hours_done']);
        $this->assertSame(
            ['enrolled' => 4, 'at_risk' => 3, 'at_risk_attending' => 2, 'at_risk_attending_passed' => 1, 'at_risk_improved' => 2],
            array_intersect_key($row, array_flip(['enrolled', 'at_risk', 'at_risk_attending', 'at_risk_attending_passed', 'at_risk_improved'])),
        );
        $this->assertEquals(50, $row['effectiveness']);
        $this->assertNull($empty['effectiveness']);
        $this->assertSame(0, $empty['enrolled']);

        // Anexo 1: todos los inscritos cuyo segundo parcial supera al primero.
        $this->assertCount(3, $summary['improved'][0]['students']);
        $this->assertSame([], $summary['improved'][1]['students']);
        // Anexo 2: registro de tutorías por asignatura.
        $this->assertCount(3, $summary['register'][0]['sessions']);
        $this->assertSame(['Pruebas unitarias'], $summary['register'][0]['sessions'][0]['topics']);
        $this->assertDatabaseCount('reporte', 0);
    }

    public function test_the_teacher_sends_the_report_and_the_coordinator_reads_it(): void
    {
        $this->scenario();
        $payload = [
            'date' => '2026-09-29',
            'tutoring_ids' => [$this->tutoring->getKey()],
            'observations' => [$this->tutoring->getKey() => 'Asistencia constante.'],
            'conclusions' => 'Las tutorías redujeron el riesgo académico.',
            'recommendations' => 'Mantener el horario.',
        ];

        $id = $this->postJson($this->path('/reports/final-pao'), $payload)->assertCreated()
            ->assertJsonPath('data.type', 'Informe final de tutorías PAO PAO 2026')
            ->assertJsonPath('data.summary.format', 'final_pao')
            ->assertJsonPath('data.summary.matrix.0.observation', 'Asistencia constante.')
            ->assertJsonPath('data.summary.conclusions', 'Las tutorías redujeron el riesgo académico.')
            ->assertJsonCount(1, 'data.summary.matrix')
            ->assertJsonMissingPath('data.summary.available_tutorings')
            ->json('data.id');
        $this->postJson($this->path('/reports/final-pao'), $payload)->assertCreated()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('reporte', 1);
        $this->assertStringContainsString('Lunes 08:00–10:00', Reporte::query()->findOrFail($id)->content);

        // El informe del mismo PAO conserva su número; el de otro PAO toma el siguiente correlativo.
        $this->getJson($this->path('/reports/final-pao/preview?date=2026-09-29'))->assertJsonPath('data.report_number', '01-TA-2026');
        $this->period->update(['nombre' => 'PAO 2026-2']);
        $this->getJson($this->path('/reports/final-pao/preview?date=2026-09-29'))->assertJsonPath('data.report_number', '02-TA-2026');
        $this->period->update(['nombre' => 'PAO 2026']);

        Sanctum::actingAs($this->coordinator, ['access-api']);
        $this->getJson(self::API.'/tutorings/'.$this->tutoring->getKey().'/reports')->assertOk()
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.summary.matrix.0.effectiveness', 50);
        // La búsqueda del coordinador encuentra el informe por tipo y por autor.
        $this->getJson(self::API.'/reports?search=Informe%20final')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->getJson(self::API.'/reports?search='.urlencode($this->teacher->nombre))->assertOk()->assertJsonPath('data.0.id', $id);
    }

    public function test_only_own_tutorings_of_the_same_period_and_career_can_be_included(): void
    {
        $payload = ['date' => '2026-09-29'];

        $this->postJson($this->path('/reports/final-pao'), [...$payload, 'tutoring_ids' => [$this->tutoring->getKey(), $this->foreign->getKey()]])
            ->assertUnprocessable()->assertJsonValidationErrors('tutoring_ids');
        $this->postJson($this->path('/reports/final-pao'), [...$payload, 'tutoring_ids' => [$this->second->getKey()]])
            ->assertUnprocessable()->assertJsonValidationErrors('tutoring_ids');
        $this->postJson($this->path('/reports/final-pao'), [...$payload, 'tutoring_ids' => []])->assertUnprocessable()->assertJsonValidationErrors('tutoring_ids');
        $this->postJson($this->path('/reports/final-pao'), ['date' => '2026-10-15', 'tutoring_ids' => [$this->tutoring->getKey()]])->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->assertDatabaseCount('reporte', 0);
    }

    public function test_another_teacher_cannot_preview_or_send_the_report(): void
    {
        Sanctum::actingAs(Usuario::factory()->withRole('docente')->create(), ['access-api']);

        $this->getJson($this->path('/reports/final-pao/preview?date=2026-09-29'))->assertForbidden();
        $this->postJson($this->path('/reports/final-pao'), ['date' => '2026-09-29', 'tutoring_ids' => [$this->tutoring->getKey()]])->assertForbidden();
    }

    private function scenario(): void
    {
        Horario::query()->create(['fk_asig_tutoria' => $this->tutoring->getKey(), 'dia_semana' => 'lunes', 'hora_inicio' => '08:00', 'hora_fin' => '10:00', 'room' => 'Lab 1', 'estado' => true]);
        Horario::query()->create(['fk_asig_tutoria' => $this->tutoring->getKey(), 'dia_semana' => 'miercoles', 'hora_inicio' => '14:00', 'hora_fin' => '15:00', 'estado' => true]);
        Horario::query()->create(['fk_asig_tutoria' => $this->tutoring->getKey(), 'dia_semana' => 'viernes', 'hora_inicio' => '07:00', 'hora_fin' => '09:00', 'estado' => false]);

        $topic = Tema::query()->create(['fk_asig_tutoria' => $this->tutoring->getKey(), 'nombre' => 'Pruebas unitarias', 'visto' => true, 'estado' => true]);
        $sessions = collect(['2026-09-21', '2026-09-23', '2026-09-26'])->map(fn (string $date) => TutoringSession::query()->create([
            'tutoring_id' => $this->tutoring->getKey(), 'recorded_by' => $this->teacher->getKey(), 'date' => $date, 'topics_covered' => true,
        ]));
        $sessions[0]->topics()->attach($topic->getKey());

        // [diagnóstica, parcial 1, parcial 2, asiste]
        foreach ([[3, 6, 8, true], [6, 7, 6, true], [4, 5, 9, false], [9, 8, 9, true]] as [$diagnostic, $first, $second, $attends]) {
            $enrollment = $this->enrollment();
            foreach (['diagnostic' => $diagnostic, 'partial' => $first, 'partial_two' => $second] as $type => $value) {
                $this->putJson($this->path('/students/'.$enrollment->getKey().'/grades/'.$type), ['value' => $value])->assertOk();
            }
            Asistencia::query()->create(['fk_inscripcion' => $enrollment->getKey(), 'fk_id_usuario' => $enrollment->fk_id_usuario, 'fecha' => '2026-09-21', 'estado_asistencia' => $attends, 'session_id' => $sessions[0]->getKey()]);
        }
    }
}

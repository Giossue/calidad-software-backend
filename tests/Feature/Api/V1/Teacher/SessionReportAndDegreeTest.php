<?php

namespace Tests\Feature\Api\V1\Teacher;

use App\Models\AsignacionDocente;
use App\Models\Asistencia;
use App\Models\Reporte;
use App\Models\Tema;
use App\Models\TemaTitulacion;
use App\Models\Usuario;
use Laravel\Sanctum\Sanctum;

class SessionReportAndDegreeTest extends TeacherTestCase
{
    public function test_session_retries_update_attendance_and_topics_without_duplicates(): void
    {
        $enrollment = $this->enrollment();
        $topic = Tema::query()->create(['fk_asig_tutoria' => $this->tutoring->getKey(), 'nombre' => 'Pruebas', 'estado' => true]);
        $data = ['date' => today()->toDateString(), 'topics_covered' => true, 'topic_ids' => [$topic->getKey()], 'attendance' => [['enrollment_id' => $enrollment->getKey(), 'present' => true]]];
        $this->putJson($this->path('/sessions'), $data)->assertOk()->assertJsonPath('data.topics_covered', true)->assertJsonPath('data.attendance.0.present', true);
        $this->putJson($this->path('/sessions'), $data)->assertOk();
        $this->assertDatabaseCount('tutoring_sessions', 1);
        $this->assertDatabaseCount('asistencia', 1);
        $this->assertTrue($topic->refresh()->visto);
        $data['attendance'][0]['present'] = false;
        $data['topics_covered'] = false;
        $this->putJson($this->path('/sessions'), $data)->assertOk()->assertJsonPath('data.attendance.0.present', false)->assertJsonCount(0, 'data.topics');
        $this->assertFalse($topic->refresh()->visto);
        Sanctum::actingAs($this->coordinator, ['access-api']);
        $this->getJson(self::API.'/tutorings/'.$this->tutoring->getKey().'/attendance')->assertOk()->assertJsonPath('data.0.present', false)->assertJsonPath('data.0.topics_covered', false);
    }

    public function test_sessions_from_previous_dates_are_read_only(): void
    {
        $enrollment = $this->enrollment();
        $past = '2026-05-04';
        $payload = ['date' => $past, 'topics_covered' => false, 'attendance' => [['enrollment_id' => $enrollment->getKey(), 'present' => true]]];

        $this->putJson($this->path('/sessions'), $payload)->assertOk();
        $this->putJson($this->path('/sessions'), [...$payload, 'attendance' => [['enrollment_id' => $enrollment->getKey(), 'present' => false]]])
            ->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->assertTrue((bool) Asistencia::query()->where('fk_inscripcion', $enrollment->getKey())->value('estado_asistencia'));
    }

    public function test_dates_duplicates_foreign_and_inactive_students_are_rejected_atomically(): void
    {
        $enrollment = $this->enrollment();
        $valid = ['date' => today()->toDateString(), 'topics_covered' => false, 'attendance' => [['enrollment_id' => $enrollment->getKey(), 'present' => true]]];
        foreach (['2026-04-30', '2026-09-30', 'bad-date'] as $date) {
            $this->putJson($this->path('/sessions'), [...$valid, 'date' => $date])->assertUnprocessable()->assertJsonValidationErrors('date');
        }
        $this->putJson($this->path('/sessions'), [...$valid, 'attendance' => [$valid['attendance'][0], $valid['attendance'][0]]])->assertUnprocessable();
        $foreign = $this->enrollment($this->createTutoring($this->otherCycle));
        $this->putJson($this->path('/sessions'), [...$valid, 'attendance' => [['enrollment_id' => $foreign->getKey(), 'present' => true]]])->assertUnprocessable();
        $enrollment->update(['estado' => false]);
        $this->putJson($this->path('/sessions'), $valid)->assertUnprocessable();
        $this->assertDatabaseCount('asistencia', 0);
        $this->assertDatabaseCount('tutoring_sessions', 0);
    }

    public function test_legacy_attendance_remains_readable_and_is_attached_when_the_session_is_saved(): void
    {
        $enrollment = $this->enrollment();
        $record = Asistencia::query()->create(['fk_inscripcion' => $enrollment->getKey(), 'fk_id_usuario' => $enrollment->fk_id_usuario, 'fecha' => today(), 'estado_asistencia' => true]);
        $this->getJson($this->path('/attendance'))->assertOk()->assertJsonPath('data.0.id', $record->getKey())->assertJsonPath('data.0.topics_covered', null);
        $this->putJson($this->path('/sessions'), ['date' => today()->toDateString(), 'topics_covered' => false, 'attendance' => [['enrollment_id' => $enrollment->getKey(), 'present' => false]]])->assertOk();
        $this->assertDatabaseCount('asistencia', 1);
        $this->assertNotNull($record->refresh()->session_id);
    }

    public function test_seen_topics_are_derived_from_all_sessions_and_keep_disabled_history(): void
    {
        $enrollment = $this->enrollment();
        $topic = Tema::query()->create(['fk_asig_tutoria' => $this->tutoring->getKey(), 'nombre' => 'Pruebas', 'estado' => true]);
        $data = ['date' => '2026-09-28', 'topics_covered' => true, 'topic_ids' => [$topic->getKey()], 'attendance' => [['enrollment_id' => $enrollment->getKey(), 'present' => true]]];
        // Cada sesión se guarda el mismo día que ocurre: las de fechas anteriores son de solo consulta.
        $this->travelTo('2026-09-28 10:00:00');
        $this->putJson($this->path('/sessions'), $data)->assertOk();
        $this->travelTo('2026-09-29 10:00:00');
        $this->putJson($this->path('/sessions'), [...$data, 'date' => '2026-09-29'])->assertOk();
        $this->putJson($this->path('/sessions'), [...$data, 'date' => '2026-09-29', 'topics_covered' => false])->assertOk();
        $this->assertTrue($topic->refresh()->visto);
        $this->putJson($this->path('/sessions'), [...$data, 'date' => '2026-09-29'])->assertOk();
        $topic->update(['estado' => false]);
        $this->putJson($this->path('/sessions'), [...$data, 'date' => '2026-09-29'])->assertOk();
        $this->putJson($this->path('/sessions'), [...$data, 'date' => '2026-09-27'])->assertUnprocessable()->assertJsonValidationErrors('topic_ids');
    }

    public function test_reports_store_a_snapshot_visible_to_the_coordinator_and_survive_later_changes(): void
    {
        $enrollment = $this->enrollment();
        $this->putJson($this->path('/students/'.$enrollment->getKey().'/grades/diagnostic'), ['value' => 8])->assertOk();
        $this->putJson($this->path('/sessions'), ['date' => today()->toDateString(), 'topics_covered' => false, 'attendance' => [['enrollment_id' => $enrollment->getKey(), 'present' => true]]])->assertOk();
        $payload = ['title' => 'Resultados de septiembre', 'observations' => 'El estudiante mejoró su comprensión.'];
        $id = $this->postJson($this->path('/reports'), $payload)->assertCreated()->assertJsonPath('data.summary.active_enrollment_count', 1)->assertJsonPath('data.summary.present_count', 1)->assertJsonPath('data.summary.knowledge_groups.Alto', 1)->json('data.id');
        $this->postJson($this->path('/reports'), $payload)->assertCreated()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('reporte', 1);
        $this->enrollment();
        $this->assertSame(1, Reporte::query()->findOrFail($id)->summary['active_enrollment_count']);
        Sanctum::actingAs($this->coordinator, ['access-api']);
        $this->getJson(self::API.'/tutorings/'.$this->tutoring->getKey().'/reports')->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonFragment(['type' => 'Resultados de septiembre']);
        $this->assertStringContainsString('El estudiante mejoró', Reporte::query()->findOrFail($id)->content);
    }

    public function test_only_own_active_degree_assignments_are_visible_and_remain_read_only(): void
    {
        $student = $this->enrollment()->estudiante;
        $topic = TemaTitulacion::query()->create(['fk_id_usuario' => $student->getKey(), 'fk_periodo' => $this->period->getKey(), 'titulo' => 'Calidad en APIs', 'descripcion' => 'Investigación', 'estado' => 'aprobado', 'fecha_propuesta' => today()]);
        $own = AsignacionDocente::query()->create(['fk_tema_tit' => $topic->getKey(), 'fk_id_usuario' => $this->teacher->getKey(), 'rol' => 'tutor', 'fecha_asignacion' => today(), 'estado' => true]);
        AsignacionDocente::query()->create(['fk_tema_tit' => $topic->getKey(), 'fk_id_usuario' => Usuario::factory()->withRole('docente')->create()->getKey(), 'rol' => 'par_academico', 'fecha_asignacion' => today(), 'estado' => true]);
        $this->getJson('/api/v1/teacher/degree-assignments?search=APIs&role=tutor')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->getKey());
        $this->getJson('/api/v1/teacher/degree-assignments?role=par_academico')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/coordination/degree-topics/'.$topic->getKey().'/approve', [])->assertForbidden();
        $own->update(['estado' => false]);
        $this->getJson('/api/v1/teacher/degree-assignments')->assertOk()->assertJsonCount(0, 'data');
    }
}

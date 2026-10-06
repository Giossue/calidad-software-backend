<?php

namespace Tests\Feature\Api\V1\Coordination;

use App\Models\PeriodoAcademico;
use App\Models\TemaTitulacion;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DegreeTopicHistoryTest extends TestCase
{
    use RefreshDatabase;

    private PeriodoAcademico $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-15 12:00:00');
        $this->period = PeriodoAcademico::query()->create([
            'nombre' => 'PAO 2026-1', 'fecha_inicio' => '2026-05-01', 'fecha_fin' => '2026-09-30', 'estado' => true,
        ]);
    }

    public function test_rejected_proposals_are_hidden_once_the_student_has_an_approved_topic(): void
    {
        $student = Usuario::factory()->withRole('estudiante')->create();
        $rejected = $this->topic($student, 'rechazado', 'Primer intento');
        $approved = $this->topic($student, 'aprobado', 'Tema aprobado');

        Sanctum::actingAs($student, ['*']);
        $this->getJson('/api/v1/student/degree-topics')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $approved->getKey());

        Sanctum::actingAs(Usuario::factory()->withRole('coordinador_titulacion')->create(), ['*']);
        $this->getJson('/api/v1/coordination/degree-topics')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $approved->getKey());
        $this->getJson('/api/v1/coordination/degree-topics?status=rechazado')->assertOk()->assertJsonCount(0, 'data');

        // El rechazo no se borra: queda en la base de datos como historial.
        $this->assertDatabaseHas('tema_titulacion', ['id_tema_tit' => $rejected->getKey(), 'estado' => 'rechazado']);
    }

    public function test_rejected_proposals_remain_visible_while_no_topic_is_approved(): void
    {
        $student = Usuario::factory()->withRole('estudiante')->create();
        $this->topic($student, 'rechazado', 'Primer intento');
        $this->topic($student, 'pendiente', 'Segundo intento');
        // El tema aprobado de otro estudiante no oculta el historial de este.
        $this->topic(Usuario::factory()->withRole('estudiante')->create(), 'aprobado', 'Tema ajeno');

        Sanctum::actingAs($student, ['*']);
        $this->getJson('/api/v1/student/degree-topics')->assertOk()->assertJsonCount(2, 'data');

        Sanctum::actingAs(Usuario::factory()->withRole('coordinador_titulacion')->create(), ['*']);
        $this->getJson('/api/v1/coordination/degree-topics?status=rechazado')->assertOk()->assertJsonCount(1, 'data');
    }

    private function topic(Usuario $student, string $status, string $title): TemaTitulacion
    {
        return TemaTitulacion::query()->create([
            'fk_id_usuario' => $student->getKey(),
            'fk_periodo' => $this->period->getKey(),
            'titulo' => $title,
            'descripcion' => 'Descripción de la propuesta de titulación.',
            'estado' => $status,
            'fecha_propuesta' => now()->toDateString(),
        ]);
    }
}

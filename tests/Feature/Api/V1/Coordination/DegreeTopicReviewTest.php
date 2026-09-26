<?php

namespace Tests\Feature\Api\V1\Coordination;

use App\Models\Paralelo;
use App\Models\PeriodoAcademico;
use App\Models\TemaTitulacion;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DegreeTopicReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordinador_titulacion_can_list_pending_degree_topics_for_current_period_and_section(): void
    {
        $period = PeriodoAcademico::query()->create([
            'nombre' => 'PAO 2026-1',
            'fecha_inicio' => '2026-05-01',
            'fecha_fin' => '2026-09-30',
            'estado' => true,
        ]);

        $sectionA = Paralelo::query()->create(['nombre' => 'A', 'estado' => true]);
        $sectionB = Paralelo::query()->create(['nombre' => 'B', 'estado' => true]);

        $period->paralelos()->attach([$sectionA->getKey(), $sectionB->getKey()]);

        // Estudiante del paralelo A
        $studentA = Usuario::factory()->withRole('estudiante')->create([
            'nombre' => 'Carlos Estudiante A',
            'correo' => 'carlos@mail.com',
            'cedula' => '0201864329',
        ]);
        $studentA->paralelos()->attach($sectionA->getKey(), ['fecha_asignacion' => now()]);

        // Estudiante del paralelo B
        $studentB = Usuario::factory()->withRole('estudiante')->create([
            'nombre' => 'Maria Estudiante B',
            'correo' => 'maria@mail.com',
            'cedula' => '0201234567',
        ]);
        $studentB->paralelos()->attach($sectionB->getKey(), ['fecha_asignacion' => now()]);

        // Tema pendiente del estudiante A
        $topicA = TemaTitulacion::query()->create([
            'fk_id_usuario' => $studentA->getKey(),
            'fk_periodo' => $period->getKey(),
            'titulo' => 'Sistema de Gestión de Calidad Universitaria',
            'descripcion' => 'Desarrollo de plataforma web para titulación',
            'estado' => 'pendiente',
            'fecha_propuesta' => '2026-06-01',
            'fecha_revision' => null,
        ]);

        // Tema pendiente del estudiante B
        TemaTitulacion::query()->create([
            'fk_id_usuario' => $studentB->getKey(),
            'fk_periodo' => $period->getKey(),
            'titulo' => 'Plataforma de IA para Diagnóstico',
            'descripcion' => 'Investigación aplicada',
            'estado' => 'pendiente',
            'fecha_propuesta' => '2026-06-02',
            'fecha_revision' => null,
        ]);

        $coordinator = Usuario::factory()->withRole('coordinador_titulacion')->create();
        Sanctum::actingAs($coordinator);

        $response = $this->getJson('/api/v1/coordination/degree-topics/pending?section_id='.$sectionA->getKey());

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $topicA->getKey())
            ->assertJsonPath('data.0.title', 'Sistema de Gestión de Calidad Universitaria')
            ->assertJsonPath('data.0.description', 'Desarrollo de plataforma web para titulación')
            ->assertJsonPath('data.0.status', 'pendiente')
            ->assertJsonPath('data.0.student.id', $studentA->getKey())
            ->assertJsonPath('data.0.student.name', 'Carlos Estudiante A')
            ->assertJsonPath('data.0.student.email', 'carlos@mail.com')
            ->assertJsonPath('data.0.section.name', 'A')
            ->assertJsonPath('data.0.academic_period.name', 'PAO 2026-1');
    }

    public function test_reviewed_topics_are_excluded_from_pending_list(): void
    {
        $period = PeriodoAcademico::query()->create([
            'nombre' => 'PAO 2026-1',
            'fecha_inicio' => '2026-05-01',
            'fecha_fin' => '2026-09-30',
            'estado' => true,
        ]);

        $section = Paralelo::query()->create(['nombre' => 'A', 'estado' => true]);
        $period->paralelos()->attach($section->getKey());

        $student = Usuario::factory()->withRole('estudiante')->create();
        $student->paralelos()->attach($section->getKey());

        $coordinator = Usuario::factory()->withRole('coordinador_titulacion')->create();

        // Tema ya aprobado/revisado
        TemaTitulacion::query()->create([
            'fk_id_usuario' => $student->getKey(),
            'fk_periodo' => $period->getKey(),
            'fk_coord_revisor' => $coordinator->getKey(),
            'titulo' => 'Tema Aprobado Previamente',
            'descripcion' => 'Revisado',
            'estado' => 'aprobado',
            'fecha_propuesta' => '2026-06-01',
            'fecha_revision' => '2026-06-10',
        ]);

        Sanctum::actingAs($coordinator);

        $response = $this->getJson('/api/v1/coordination/degree-topics/pending?section_id='.$section->getKey());

        $response->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_coordinador_titulacion_can_view_single_topic_detail(): void
    {
        $period = PeriodoAcademico::query()->create([
            'nombre' => 'PAO 2026-1',
            'fecha_inicio' => '2026-05-01',
            'fecha_fin' => '2026-09-30',
            'estado' => true,
        ]);

        $student = Usuario::factory()->withRole('estudiante')->create([
            'nombre' => 'Estudiante Detalle',
        ]);

        $topic = TemaTitulacion::query()->create([
            'fk_id_usuario' => $student->getKey(),
            'fk_periodo' => $period->getKey(),
            'titulo' => 'Tema para Detalle',
            'descripcion' => 'Descripción detallada',
            'estado' => 'pendiente',
            'fecha_propuesta' => '2026-06-01',
        ]);

        $coordinator = Usuario::factory()->withRole('coordinador_titulacion')->create();
        Sanctum::actingAs($coordinator);

        $response = $this->getJson('/api/v1/coordination/degree-topics/'.$topic->getKey());

        $response->assertOk()
            ->assertJsonPath('data.id', $topic->getKey())
            ->assertJsonPath('data.title', 'Tema para Detalle')
            ->assertJsonPath('data.student.name', 'Estudiante Detalle');
    }

    public function test_unauthorized_roles_cannot_review_degree_topics(): void
    {
        $student = Usuario::factory()->withRole('estudiante')->create();
        Sanctum::actingAs($student);

        $this->getJson('/api/v1/coordination/degree-topics/pending')->assertForbidden();
    }
}

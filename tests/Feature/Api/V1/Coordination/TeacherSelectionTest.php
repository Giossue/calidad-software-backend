<?php

namespace Tests\Feature\Api\V1\Coordination;

use App\Models\AsignacionDocente;
use App\Models\PeriodoAcademico;
use App\Models\TemaTitulacion;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeacherSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordinador_titulacion_can_list_all_active_teachers_with_assignment_metrics(): void
    {
        $period = PeriodoAcademico::query()->create([
            'nombre' => 'PAO 2026-1',
            'fecha_inicio' => '2026-05-01',
            'fecha_fin' => '2026-09-30',
            'estado' => true,
        ]);

        $student = Usuario::factory()->withRole('estudiante')->create();

        $teacher1 = Usuario::factory()->withRole('docente')->create(['nombre' => 'Ing. Juan Docente Tutor']);
        $teacher2 = Usuario::factory()->withRole('docente')->create(['nombre' => 'Dra. María Docente Par']);

        // Crear temas y asignaciones previas para teacher1
        $topic1 = TemaTitulacion::query()->create([
            'fk_id_usuario' => $student->getKey(),
            'fk_periodo' => $period->getKey(),
            'titulo' => 'Tema Previo 1',
            'estado' => 'aprobado',
        ]);
        $topic2 = TemaTitulacion::query()->create([
            'fk_id_usuario' => $student->getKey(),
            'fk_periodo' => $period->getKey(),
            'titulo' => 'Tema Previo 2',
            'estado' => 'aprobado',
        ]);

        AsignacionDocente::query()->create([
            'fk_tema_tit' => $topic1->getKey(),
            'fk_id_usuario' => $teacher1->getKey(),
            'rol' => 'tutor',
            'estado' => true,
        ]);
        AsignacionDocente::query()->create([
            'fk_tema_tit' => $topic2->getKey(),
            'fk_id_usuario' => $teacher1->getKey(),
            'rol' => 'tutor',
            'estado' => true,
        ]);
        AsignacionDocente::query()->create([
            'fk_tema_tit' => $topic1->getKey(),
            'fk_id_usuario' => $teacher2->getKey(),
            'rol' => 'par_academico',
            'estado' => true,
        ]);

        $coordinator = Usuario::factory()->withRole('coordinador_titulacion')->create();
        Sanctum::actingAs($coordinator);

        $response = $this->getJson('/api/v1/coordination/teachers');

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        // Ambos docentes están disponibles para ser seleccionados
        $response->assertJsonFragment([
            'id' => $teacher1->getKey(),
            'name' => 'Ing. Juan Docente Tutor',
            'active_tutorships_count' => 2,
            'active_peer_reviews_count' => 0,
        ]);

        $response->assertJsonFragment([
            'id' => $teacher2->getKey(),
            'name' => 'Dra. María Docente Par',
            'active_tutorships_count' => 0,
            'active_peer_reviews_count' => 1,
        ]);
    }

    public function test_inactive_teachers_and_non_teachers_are_excluded(): void
    {
        Usuario::factory()->withRole('docente')->create([
            'nombre' => 'Docente Inactivo',
            'estado' => false,
        ]);

        Usuario::factory()->withRole('estudiante')->create([
            'nombre' => 'Estudiante No Docente',
            'estado' => true,
        ]);

        $activeTeacher = Usuario::factory()->withRole('docente')->create([
            'nombre' => 'Docente Activo Válido',
            'estado' => true,
        ]);

        $coordinator = Usuario::factory()->withRole('coordinador_titulacion')->create();
        Sanctum::actingAs($coordinator);

        $response = $this->getJson('/api/v1/coordination/teachers');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $activeTeacher->getKey());
    }

    public function test_teachers_can_be_filtered_by_search_term(): void
    {
        Usuario::factory()->withRole('docente')->create(['nombre' => 'Alberto Romero']);
        Usuario::factory()->withRole('docente')->create(['nombre' => 'Beatriz Salazar']);

        $coordinator = Usuario::factory()->withRole('coordinador_titulacion')->create();
        Sanctum::actingAs($coordinator);

        $response = $this->getJson('/api/v1/coordination/teachers?search=Alberto');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Alberto Romero');
    }

    public function test_unauthorized_user_cannot_access_teachers_catalog(): void
    {
        $student = Usuario::factory()->withRole('estudiante')->create();
        Sanctum::actingAs($student);

        $this->getJson('/api/v1/coordination/teachers')->assertForbidden();
    }
}

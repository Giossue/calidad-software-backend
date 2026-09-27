<?php

namespace Tests\Feature\Migrations;

use App\Models\ObservacionTitulacion;
use App\Models\PeriodoAcademico;
use App\Models\TemaTitulacion;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class DegreeCoordinationMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rerunning_compatibility_migration_preserves_existing_text_states_and_observations(): void
    {
        $student = Usuario::factory()->withRole('estudiante')->create();
        $coordinator = Usuario::factory()->withRole('coordinador_titulacion')->create();
        $period = PeriodoAcademico::query()->create([
            'nombre' => 'PAO 2026', 'fecha_inicio' => '2026-09-01', 'fecha_fin' => '2027-02-28', 'estado' => true,
        ]);
        foreach (['pendiente', 'aprobado', 'rechazado'] as $status) {
            $topic = TemaTitulacion::query()->create([
                'fk_id_usuario' => $student->getKey(), 'fk_periodo' => $period->getKey(),
                'titulo' => 'Propuesta '.$status, 'descripcion' => 'Contenido previo que debe conservarse.',
                'estado' => $status, 'fecha_propuesta' => '2026-09-01',
                'fk_coord_revisor' => $status === 'pendiente' ? null : $coordinator->getKey(),
                'fecha_revision' => $status === 'pendiente' ? null : '2026-09-02',
            ]);
            ObservacionTitulacion::query()->create([
                'fk_tema_tit' => $topic->getKey(), 'fk_coord_tit' => $coordinator->getKey(),
                'descripcion' => str_repeat('á', 1000), 'fecha_registro' => '2026-09-02',
            ]);
        }
        $beforeTopics = DB::table('tema_titulacion')->orderBy('id_tema_tit')->get()->toJson();
        $beforeObservations = DB::table('observacion_titulacion')->orderBy('id_obs_tit')->get()->toJson();
        $migration = require database_path('migrations/2026_09_27_010000_align_degree_coordination_baseline.php');
        $migration->up();
        $migration->up();

        $this->assertSame($beforeTopics, DB::table('tema_titulacion')->orderBy('id_tema_tit')->get()->toJson());
        $this->assertSame($beforeObservations, DB::table('observacion_titulacion')->orderBy('id_obs_tit')->get()->toJson());
        $this->assertDatabaseCount('tema_titulacion', 3);
        $this->assertDatabaseCount('observacion_titulacion', 3);
    }

    public function test_automatic_rollback_is_rejected_instead_of_losing_text_states_or_observations(): void
    {
        $migration = require database_path('migrations/2026_09_27_010000_align_degree_coordination_baseline.php');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no admite rollback automático');

        $migration->down();
    }
}

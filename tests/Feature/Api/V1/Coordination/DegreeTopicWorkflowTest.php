<?php

namespace Tests\Feature\Api\V1\Coordination;

use App\Actions\Coordination\ApproveDegreeTopic;
use App\Actions\Coordination\RejectDegreeTopic;
use App\Actions\Coordination\UpdateAcademicPeers;
use App\Models\AsignacionDocente;
use App\Models\ObservacionTitulacion;
use App\Models\Paralelo;
use App\Models\PeriodoAcademico;
use App\Models\TemaTitulacion;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DegreeTopicWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private PeriodoAcademico $period;

    private Usuario $coordinator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->period = PeriodoAcademico::query()->create([
            'nombre' => 'PAO vigente', 'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31', 'estado' => true,
        ]);
        $this->coordinator = Usuario::factory()->withRole('coordinador_titulacion')->create();
    }

    public function test_listing_defaults_to_all_statuses_and_sections_in_current_period(): void
    {
        $sectionA = $this->section('A');
        $sectionB = $this->section('B');
        $pending = $this->topic(section: $sectionA);
        $approved = $this->topic('aprobado', $sectionB);
        $rejected = $this->topic('rechazado', $sectionB);
        $oldPeriod = PeriodoAcademico::query()->create([
            'nombre' => 'PAO anterior', 'fecha_inicio' => '2025-01-01',
            'fecha_fin' => '2025-12-31', 'estado' => false,
        ]);
        $this->topic()->update(['fk_periodo' => $oldPeriod->getKey()]);
        Sanctum::actingAs($this->coordinator, ['access-api']);

        $response = $this->getJson('/api/v1/coordination/degree-topics')->assertOk()
            ->assertJsonCount(3, 'data')->assertJsonPath('meta.filter_section_id', null)
            ->assertJsonPath('meta.filter_status', null);
        $this->assertEqualsCanonicalizing(
            [$pending->getKey(), $approved->getKey(), $rejected->getKey()],
            array_column($response->json('data'), 'id')
        );

        $this->getJson('/api/v1/coordination/degree-topics?status=aprobado&section_id='.$sectionB->getKey())
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $approved->getKey());
        $this->getJson('/api/v1/coordination/degree-topics?status=aprobado&section_id='.$sectionA->getKey())
            ->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/coordination/degree-topics/pending')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $pending->getKey())
            ->assertJsonPath('meta.filter_section_id', $sectionA->getKey());
    }

    public function test_search_matches_topic_or_student_and_remains_scoped_to_period_and_status(): void
    {
        $topic = $this->topic('rechazado');
        $topic->update(['titulo' => 'Diagnóstico automatizado', 'descripcion' => 'Evaluación estructural']);
        $topic->estudiante->update(['nombre' => 'Andrea Prueba', 'correo' => 'andrea.fixture@example.test']);
        $this->topic();
        Sanctum::actingAs($this->coordinator, ['access-api']);

        foreach (['diagnóstico', 'estructural', 'Andrea', 'andrea.fixture@example.test', $topic->estudiante->cedula] as $search) {
            $this->getJson('/api/v1/coordination/degree-topics?'.http_build_query(['search' => $search, 'status' => 'rechazado']))
                ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $topic->getKey());
        }
        $this->getJson('/api/v1/coordination/degree-topics?search=Andrea&status=pendiente')
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_list_filters_are_validated_and_absent_period_returns_404(): void
    {
        Sanctum::actingAs($this->coordinator, ['access-api']);
        $this->getJson('/api/v1/coordination/degree-topics?status=invalid&section_id=invalid&search[]=bad')
            ->assertUnprocessable()->assertJsonValidationErrors(['status', 'section_id', 'search']);
        $this->period->update(['estado' => false]);
        $this->getJson('/api/v1/coordination/degree-topics')->assertNotFound();
        $this->getJson('/api/v1/coordination/degree-topics/pending')->assertNotFound();
    }

    public function test_resources_eager_load_observations_and_only_active_assignments_in_all_views(): void
    {
        $topic = $this->topic('aprobado');
        $other = $this->topic();
        $active = $this->assignment($topic, 'tutor');
        $inactive = $this->assignment($topic, 'par_academico', false);
        ObservacionTitulacion::query()->create([
            'fk_tema_tit' => $topic->getKey(), 'fk_coord_tit' => $this->coordinator->getKey(),
            'descripcion' => 'Observación visible', 'fecha_registro' => now()->toDateString(),
        ]);
        Sanctum::actingAs($this->coordinator, ['access-api']);
        Model::preventLazyLoading();

        try {
            $this->getJson('/api/v1/coordination/degree-topics')->assertOk()->assertJsonCount(2, 'data');
            $this->getJson('/api/v1/coordination/degree-topics/'.$topic->getKey())->assertOk()
                ->assertJsonCount(1, 'data.assignments')->assertJsonPath('data.assignments.0.id', $active->getKey())
                ->assertJsonPath('data.observations.0.observation', 'Observación visible');
            Sanctum::actingAs($topic->estudiante, ['access-api']);
            $this->getJson('/api/v1/student/degree-topics?student_id='.$other->fk_id_usuario)->assertOk()
                ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $topic->getKey())
                ->assertJsonCount(1, 'data.0.assignments')
                ->assertJsonPath('data.0.observations.0.coordinator.id', $this->coordinator->getKey());
            $this->assertDatabaseHas('asignacion_docente', ['id_asignacion' => $inactive->getKey(), 'estado' => false]);
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    public function test_real_partial_2fa_token_cannot_access_any_degree_workflow_route(): void
    {
        $topic = $this->topic();
        $token = $this->coordinator->createToken('2fa challenge', ['two-factor:challenge'])->plainTextToken;
        $this->withToken($token);
        foreach ($this->workflowRequests($topic) as [$method, $url]) {
            $this->json($method, $url)->assertForbidden();
        }
        $this->assertSame('pendiente', $topic->fresh()->estado);
    }

    public function test_real_full_access_token_can_read_the_degree_dashboard(): void
    {
        $topic = $this->topic();
        $token = $this->coordinator->createToken('full access', ['*'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/coordination/degree-topics')
            ->assertOk()->assertJsonPath('data.0.id', $topic->getKey());
    }

    public function test_inactive_or_unverified_account_cannot_access_any_degree_workflow_route(): void
    {
        $topic = $this->topic();
        foreach ([['estado' => false], ['estado' => true, 'email_verified_at' => null]] as $state) {
            $this->coordinator->update($state);
            Sanctum::actingAs($this->coordinator->fresh(), ['access-api']);
            foreach ($this->workflowRequests($topic) as [$method, $url]) {
                $this->json($method, $url)->assertForbidden();
            }
        }
    }

    #[DataProvider('unauthorizedRoles')]
    public function test_unrelated_roles_cannot_access_coordination(string $role): void
    {
        $topic = $this->topic();
        Sanctum::actingAs(Usuario::factory()->withRole($role)->create(), ['access-api']);
        foreach ($this->workflowRequests($topic) as [$method, $url]) {
            if ($url === '/api/v1/student/degree-topics' && $role === 'estudiante') {
                continue;
            }
            $this->json($method, $url)->assertForbidden();
        }
    }

    public static function unauthorizedRoles(): array
    {
        return [['docente'], ['coordinador_carrera'], ['estudiante']];
    }

    public function test_administrator_can_review_and_student_cannot_read_another_students_topic(): void
    {
        $topic = $this->topic();
        $admin = Usuario::factory()->withRole('administrador')->create();
        Sanctum::actingAs($admin, ['access-api']);
        $this->getJson('/api/v1/coordination/degree-topics')->assertOk();
        $this->postJson('/api/v1/coordination/degree-topics/'.$topic->getKey().'/reject', ['observation' => 'Revisado por administración'])
            ->assertOk()->assertJsonPath('data.reviewer.id', $admin->getKey());
        Sanctum::actingAs(Usuario::factory()->withRole('estudiante')->create(), ['access-api']);
        $this->getJson('/api/v1/student/degree-topics?student_id='.$topic->fk_id_usuario)->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/coordination/degree-topics/'.$topic->getKey())->assertForbidden();
    }

    public function test_rejected_topic_cannot_be_reapproved_or_receive_peer_reassignments(): void
    {
        $topic = $this->topic('rechazado');
        $tutor = Usuario::factory()->withRole('docente')->create();
        $peer = Usuario::factory()->withRole('docente')->create();
        Sanctum::actingAs($this->coordinator, ['access-api']);
        $this->postJson('/api/v1/coordination/degree-topics/'.$topic->getKey().'/approve', [
            'tutor_id' => $tutor->getKey(), 'peer_ids' => [$peer->getKey()],
        ])->assertUnprocessable()->assertJsonValidationErrors('topic');
        $this->putJson('/api/v1/coordination/degree-topics/'.$topic->getKey().'/peers', ['peer_ids' => [$peer->getKey()]])
            ->assertUnprocessable()->assertJsonValidationErrors('topic');
        $this->assertDatabaseCount('asignacion_docente', 0);
        $this->assertSame('rechazado', $topic->fresh()->estado);
    }

    public function test_peers_require_an_approved_topic_with_an_active_tutor(): void
    {
        $topic = $this->topic();
        $peer = Usuario::factory()->withRole('docente')->create();
        Sanctum::actingAs($this->coordinator, ['access-api']);
        $url = '/api/v1/coordination/degree-topics/'.$topic->getKey().'/peers';

        $this->putJson($url, ['peer_ids' => [$peer->getKey()]])
            ->assertUnprocessable()->assertJsonValidationErrors('topic');
        $topic->update(['estado' => 'aprobado', 'fecha_revision' => now()->toDateString(), 'fk_coord_revisor' => $this->coordinator->getKey()]);
        $this->putJson($url, ['peer_ids' => [$peer->getKey()]])
            ->assertUnprocessable()->assertJsonValidationErrors('tutor_id');
        $tutor = $this->assignment($topic, 'tutor');
        $tutor->docente->update(['estado' => false]);
        $this->putJson($url, ['peer_ids' => [$peer->getKey()]])
            ->assertUnprocessable()->assertJsonValidationErrors('tutor_id');
    }

    public function test_observation_limit_is_preserved_in_review_and_standalone_notes(): void
    {
        $topic = $this->topic();
        $observation = str_repeat('A', 1000);
        Sanctum::actingAs($this->coordinator, ['access-api']);
        $url = '/api/v1/coordination/degree-topics/'.$topic->getKey();

        $this->postJson($url.'/observations', ['observation' => $observation])->assertCreated();
        $this->postJson($url.'/reject', ['observation' => $observation])->assertOk()
            ->assertJsonCount(2, 'data.observations')
            ->assertJsonPath('data.observations.1.observation', $observation);
    }

    public function test_stale_pending_models_cannot_overwrite_a_review_committed_before_the_action(): void
    {
        $topic = $this->topic();
        $tutor = Usuario::factory()->withRole('docente')->create();
        $peer = Usuario::factory()->withRole('docente')->create();
        TemaTitulacion::query()->whereKey($topic->getKey())->update([
            'estado' => 'rechazado', 'fecha_revision' => now()->toDateString(), 'fk_coord_revisor' => $this->coordinator->getKey(),
        ]);
        foreach (['approve', 'reject'] as $operation) {
            try {
                if ($operation === 'approve') {
                    app(ApproveDegreeTopic::class)->handle($topic, $this->coordinator, $tutor->getKey(), [$peer->getKey()]);
                } else {
                    app(RejectDegreeTopic::class)->handle($topic, $this->coordinator, 'No debe persistir');
                }
                $this->fail('A stale pending model must not override the locked current state.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('topic', $exception->errors());
            }
        }
        $this->assertSame('rechazado', $topic->fresh()->estado);
        $this->assertDatabaseCount('asignacion_docente', 0);
        $this->assertDatabaseCount('observacion_titulacion', 0);
    }

    public function test_assignment_actions_recheck_active_teachers_and_roll_back_without_partial_changes(): void
    {
        $topic = $this->topic();
        $tutor = Usuario::factory()->withRole('docente')->create();
        $peer = Usuario::factory()->withRole('docente')->create(['estado' => false]);
        try {
            app(ApproveDegreeTopic::class)->handle($topic, $this->coordinator, $tutor->getKey(), [$peer->getKey()]);
            $this->fail('Inactive teachers must be rejected inside the transaction.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('peer_ids.0', $exception->errors());
        }
        $this->assertSame('pendiente', $topic->fresh()->estado);
        $this->assertDatabaseCount('asignacion_docente', 0);

        $topic->update(['estado' => 'aprobado', 'fecha_revision' => now()->toDateString(), 'fk_coord_revisor' => $this->coordinator->getKey()]);
        $this->assignment($topic, 'tutor');
        $oldPeer = $this->assignment($topic, 'par_academico');
        try {
            app(UpdateAcademicPeers::class)->handle($topic, [$peer->getKey()]);
            $this->fail('Invalid reassignment must leave current peers unchanged.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('peer_ids.0', $exception->errors());
        }
        $this->assertTrue($oldPeer->fresh()->estado);
    }

    public function test_reassigning_a_former_peer_reuses_history_without_duplicate_rows(): void
    {
        $topic = $this->topic('aprobado');
        $this->assignment($topic, 'tutor');
        $formerPeer = $this->assignment($topic, 'par_academico', false);
        $currentPeer = $this->assignment($topic, 'par_academico');
        Sanctum::actingAs($this->coordinator, ['access-api']);
        $this->putJson('/api/v1/coordination/degree-topics/'.$topic->getKey().'/peers', ['peer_ids' => [$formerPeer->fk_id_usuario]])
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.assignment_id', $formerPeer->getKey());
        $this->assertTrue($formerPeer->fresh()->estado);
        $this->assertFalse($currentPeer->fresh()->estado);
        $this->assertDatabaseCount('asignacion_docente', 3);
    }

    public function test_malformed_teacher_identifiers_return_validation_errors_instead_of_server_errors(): void
    {
        $topic = $this->topic();
        Sanctum::actingAs($this->coordinator, ['access-api']);
        $this->postJson('/api/v1/coordination/degree-topics/'.$topic->getKey().'/approve', [
            'tutor_id' => ['invalid'], 'peer_ids' => [['invalid']],
        ])->assertUnprocessable()->assertJsonValidationErrors(['tutor_id', 'peer_ids.0']);
    }

    private function topic(string $status = 'pendiente', ?Paralelo $section = null): TemaTitulacion
    {
        $student = Usuario::factory()->withRole('estudiante')->create();
        if ($section !== null) {
            $student->paralelos()->attach($section->getKey());
        }

        return TemaTitulacion::query()->create([
            'fk_id_usuario' => $student->getKey(), 'fk_periodo' => $this->period->getKey(),
            'titulo' => 'Tema '.fake()->unique()->numerify('######'), 'descripcion' => 'Propuesta de investigación',
            'estado' => $status, 'fecha_propuesta' => now()->toDateString(),
            'fecha_revision' => $status === 'pendiente' ? null : now()->toDateString(),
            'fk_coord_revisor' => $status === 'pendiente' ? null : $this->coordinator->getKey(),
        ]);
    }

    private function section(string $name): Paralelo
    {
        $section = Paralelo::query()->create(['nombre' => $name, 'estado' => true]);
        $this->period->paralelos()->attach($section->getKey());

        return $section;
    }

    private function assignment(TemaTitulacion $topic, string $role, bool $active = true): AsignacionDocente
    {
        return AsignacionDocente::query()->create([
            'fk_tema_tit' => $topic->getKey(), 'fk_id_usuario' => Usuario::factory()->withRole('docente')->create()->getKey(),
            'rol' => $role, 'estado' => $active, 'fecha_asignacion' => now()->toDateString(),
        ]);
    }

    private function workflowRequests(TemaTitulacion $topic): array
    {
        $base = '/api/v1/coordination/degree-topics/'.$topic->getKey();

        return [
            ['GET', '/api/v1/coordination/degree-topics'], ['GET', '/api/v1/coordination/degree-topics/pending'],
            ['GET', $base], ['POST', $base.'/approve'], ['POST', $base.'/reject'],
            ['GET', $base.'/peers'], ['PUT', $base.'/peers'], ['POST', $base.'/observations'],
            ['GET', '/api/v1/coordination/teachers'], ['GET', '/api/v1/academic-periods/current'],
            ['GET', '/api/v1/academic-periods/current/sections'], ['POST', '/api/v1/academic-periods/current/sections'],
            ['GET', '/api/v1/student/degree-topics'],
        ];
    }
}

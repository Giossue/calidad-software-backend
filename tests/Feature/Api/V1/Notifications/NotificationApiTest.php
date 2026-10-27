<?php

namespace Tests\Feature\Api\V1\Notifications;

use App\Models\PeriodoAcademico;
use App\Models\TemaTitulacion;
use App\Models\Usuario;
use App\Notifications\ActionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_lists_counts_and_marks_only_their_own_notifications(): void
    {
        $user = Usuario::factory()->student()->create();
        $other = Usuario::factory()->create();
        $user->notify(new ActionNotification('test', 'Primero', 'Cuerpo 1', '/panel/users'));
        $user->notify(new ActionNotification('test', 'Segundo', 'Cuerpo 2'));
        $other->notify(new ActionNotification('test', 'Ajeno', 'No es tuyo'));
        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/notifications/unread-count')->assertOk()->assertJsonPath('data.unread_count', 2);
        $response = $this->getJson('/api/v1/notifications')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.unread_count', 2)
            ->assertJsonStructure(['data' => [['id', 'kind', 'title', 'body', 'url', 'read_at', 'created_at']]]);

        $first = collect($response->json('data'))->firstWhere('title', 'Primero');
        $this->assertSame('/panel/users', $first['url']);
        $this->patchJson('/api/v1/notifications/'.$first['id'].'/read')->assertOk()->assertJsonPath('data.read_at', fn ($value) => $value !== null);
        $this->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.unread_count', 1);

        $foreign = $other->notifications()->value('id');
        $this->patchJson('/api/v1/notifications/'.$foreign.'/read')->assertNotFound();

        $this->patchJson('/api/v1/notifications/read-all')->assertNoContent();
        $this->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.unread_count', 0);
        $this->assertSame(1, $other->unreadNotifications()->count());
    }

    public function test_read_notifications_older_than_thirty_days_are_removed(): void
    {
        $user = Usuario::factory()->student()->create();
        $user->notify(new ActionNotification('test', 'Antigua leída', 'x'));
        $user->notify(new ActionNotification('test', 'Antigua sin leer', 'y'));
        $user->notifications()->where('data->title', 'Antigua leída')->update(['read_at' => now()->subDays(31)]);
        $user->notifications()->update(['created_at' => now()->subDays(40)]);
        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/notifications')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Antigua sin leer');
    }

    public function test_degree_topic_flow_notifies_coordinators_student_and_teachers_but_not_the_actor(): void
    {
        $period = PeriodoAcademico::query()->create(['nombre' => 'PAO 2026-1', 'fecha_inicio' => '2026-05-01', 'fecha_fin' => '2026-09-30', 'estado' => true]);
        $student = Usuario::factory()->student()->create(['nombre' => 'Ana Torres']);
        $coordinator = Usuario::factory()->withRole('coordinador_titulacion')->create();
        $otherCoordinator = Usuario::factory()->withRole('coordinador_titulacion')->create();
        $tutor = Usuario::factory()->withRole('docente')->create();
        $peer1 = Usuario::factory()->withRole('docente')->create();
        $peer2 = Usuario::factory()->withRole('docente')->create();

        $topic = TemaTitulacion::query()->create([
            'fk_id_usuario' => $student->getKey(), 'fk_periodo' => $period->getKey(),
            'titulo' => 'Auditoría de seguridad', 'descripcion' => 'Propuesta', 'estado' => 'pendiente',
            'fecha_propuesta' => now()->toDateString(),
        ]);

        $this->assertSame(['Nueva propuesta de titulación'], $this->titles($coordinator));
        $this->assertSame(['Nueva propuesta de titulación'], $this->titles($otherCoordinator));

        Sanctum::actingAs($coordinator, ['*']);
        $this->postJson("/api/v1/coordination/degree-topics/{$topic->getKey()}/approve", [
            'tutor_id' => $tutor->getKey(), 'peer_ids' => [$peer1->getKey(), $peer2->getKey()],
        ])->assertOk();

        $this->assertSame(['Tu propuesta fue aprobada'], $this->titles($student));
        $this->assertSame(['Fuiste designado tutor'], $this->titles($tutor));
        $this->assertSame(['Fuiste designado par académico'], $this->titles($peer1));
        $this->assertSame(['Nueva propuesta de titulación'], $this->titles($coordinator));
        $this->assertSame('/panel/student-degree-assignments', $student->notifications()->first()->data['url']);
    }

    /** @return array<int, string> */
    private function titles(Usuario $user): array
    {
        return $user->notifications()->get()->pluck('data.title')->all();
    }
}

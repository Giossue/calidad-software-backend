<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TourTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_have_not_completed_the_tour(): void
    {
        Sanctum::actingAs(Usuario::factory()->withRole('docente')->create(), ['access-api']);

        $this->getJson('/api/v1/auth/user')->assertOk()->assertJsonPath('data.tour_completed', false);
    }

    public function test_completing_the_tour_is_recorded_once(): void
    {
        $user = Usuario::factory()->withRole('docente')->create();
        Sanctum::actingAs($user, ['access-api']);

        Carbon::setTestNow('2026-11-05 10:00:00');
        $this->postJson('/api/v1/auth/tour/complete')->assertOk()->assertJsonPath('data.tour_completed', true);
        Carbon::setTestNow('2026-11-06 10:00:00');
        $this->postJson('/api/v1/auth/tour/complete')->assertOk();

        $this->assertSame('2026-11-05 10:00:00', $user->refresh()->tour_completed_at?->toDateTimeString());
        $this->getJson('/api/v1/auth/user')->assertJsonPath('data.tour_completed', true);
    }

    public function test_guests_cannot_complete_the_tour(): void
    {
        $this->postJson('/api/v1/auth/tour/complete')->assertUnauthorized();
    }
}

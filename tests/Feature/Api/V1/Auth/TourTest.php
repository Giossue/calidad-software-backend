<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TourTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_have_not_completed_any_tour(): void
    {
        Sanctum::actingAs(Usuario::factory()->withRole('docente')->create(), ['access-api']);

        $this->getJson('/api/v1/auth/user')->assertOk()->assertJsonPath('data.completed_tours', []);
    }

    public function test_each_tour_is_recorded_once(): void
    {
        $user = Usuario::factory()->withRole('docente')->create();
        Sanctum::actingAs($user, ['access-api']);

        $this->postJson('/api/v1/auth/tours/complete', ['tour' => 'general'])->assertOk()->assertJsonPath('data.completed_tours', ['general']);
        $this->postJson('/api/v1/auth/tours/complete', ['tour' => 'teacher-tutorings/grades'])->assertOk();
        $this->postJson('/api/v1/auth/tours/complete', ['tour' => 'general'])->assertOk();

        $this->assertSame(['general', 'teacher-tutorings/grades'], $user->refresh()->completed_tours);
        $this->getJson('/api/v1/auth/user')->assertJsonPath('data.completed_tours', ['general', 'teacher-tutorings/grades']);
    }

    public function test_tour_identifiers_are_validated(): void
    {
        Sanctum::actingAs(Usuario::factory()->withRole('docente')->create(), ['access-api']);

        foreach (['', 'Con Mayúsculas', 'a/b/c', '<script>', str_repeat('a', 81)] as $tour) {
            $this->postJson('/api/v1/auth/tours/complete', ['tour' => $tour])->assertUnprocessable()->assertJsonValidationErrors('tour');
        }
    }

    public function test_guests_cannot_complete_tours(): void
    {
        $this->postJson('/api/v1/auth/tours/complete', ['tour' => 'general'])->assertUnauthorized();
    }
}

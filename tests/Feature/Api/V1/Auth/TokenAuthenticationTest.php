<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class TokenAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_issue_a_token_with_valid_credentials(): void
    {
        $user = User::factory()->create(['password_hash' => 'password']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->correo,
            'password' => 'password',
            'device_name' => 'frontend-web',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.id', $user->getKey())
            ->assertJsonPath('data.user.email', $user->correo)
            ->assertJsonStructure(['data' => ['access_token', 'expires_at']]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->getKey(),
            'name' => 'frontend-web',
        ]);
    }

    public function test_invalid_credentials_are_rejected_without_issuing_a_token(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->correo,
            'password' => 'incorrecta',
            'device_name' => 'frontend-web',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_two_factor_account_does_not_bypass_its_challenge(): void
    {
        $user = User::factory()->create([
            'password_hash' => 'password',
            'two_factor_confirmed_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->correo,
            'password' => 'password',
            'device_name' => 'frontend-web',
        ])->assertConflict()
            ->assertJsonPath('code', 'two_factor_required')
            ->assertJsonStructure(['data' => ['challenge_token', 'expires_at']]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->getKey(),
            'name' => 'two-factor-challenge:frontend-web',
        ]);
    }

    public function test_protected_endpoint_requires_a_bearer_token(): void
    {
        $this->getJson('/api/v1/auth/user')->assertUnauthorized();
    }

    public function test_authenticated_user_can_be_retrieved_and_logged_out(): void
    {
        $user = User::factory()->create();
        $plainTextToken = $user->createToken('frontend-web')->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$plainTextToken];

        $this->withHeaders($headers)->getJson('/api/v1/auth/user')
            ->assertOk()->assertJsonPath('data.id', $user->getKey());
        $this->withHeaders($headers)->deleteJson('/api/v1/auth/logout')
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertNull(PersonalAccessToken::findToken($plainTextToken));
    }
}

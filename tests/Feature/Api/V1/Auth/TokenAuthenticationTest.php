<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class TokenAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_issue_a_token_with_valid_credentials(): void
    {
        $user = Usuario::factory()->create(['password_hash' => 'password']);

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
        $user = Usuario::factory()->create();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->correo,
            'password' => 'incorrecta',
            'device_name' => 'frontend-web',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_two_factor_account_does_not_bypass_its_challenge(): void
    {
        $user = Usuario::factory()->create([
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
        $user = Usuario::factory()->create();
        $plainTextToken = $user->createToken('frontend-web')->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$plainTextToken];

        $this->withHeaders($headers)->getJson('/api/v1/auth/user')
            ->assertOk()->assertJsonPath('data.id', $user->getKey());
        $this->withHeaders($headers)->deleteJson('/api/v1/auth/logout')
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertNull(PersonalAccessToken::findToken($plainTextToken));
    }

    public function test_coordinador_titulacion_can_login_with_valid_credentials(): void
    {
        $coordinador = Usuario::factory()
            ->withRole('coordinador_titulacion')
            ->create([
                'password_hash' => 'password123',
                'estado' => true,
            ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $coordinador->correo,
            'password' => 'password123',
            'device_name' => 'titulacion-web',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.role', 'coordinador_titulacion')
            ->assertJsonPath('data.user.email', $coordinador->correo)
            ->assertJsonStructure(['data' => ['access_token', 'expires_at']]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $coordinador->getKey(),
            'name' => 'titulacion-web',
        ]);
    }

    public function test_coordinador_titulacion_receives_generic_error_on_invalid_credentials(): void
    {
        $coordinador = Usuario::factory()
            ->withRole('coordinador_titulacion')
            ->create([
                'password_hash' => 'password123',
                'estado' => true,
            ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $coordinador->correo,
            'password' => 'clave_invalida',
            'device_name' => 'titulacion-web',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath('errors.email.0', 'Las credenciales proporcionadas no son correctas.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_coordinador_titulacion_can_logout_and_token_is_invalidated(): void
    {
        $coordinador = Usuario::factory()
            ->withRole('coordinador_titulacion')
            ->create([
                'estado' => true,
            ]);
        $token = $coordinador->createToken('titulacion-web')->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$token];

        $response = $this->withHeaders($headers)->deleteJson('/api/v1/auth/logout');
        $response->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $coordinador->getKey(),
        ]);
        $this->assertNull(PersonalAccessToken::findToken($token));

        $this->app['auth']->forgetGuards();
        $this->withHeaders($headers)->getJson('/api/v1/auth/user')->assertUnauthorized();
    }
}

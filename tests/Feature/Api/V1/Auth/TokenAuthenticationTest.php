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

    public function test_login_attempts_are_limited_per_email_and_not_shared_between_accounts(): void
    {
        $user = Usuario::factory()->create();
        $other = Usuario::factory()->create(['password_hash' => 'password']);
        $attempt = fn (string $email, string $password) => $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => $password,
            'device_name' => 'frontend-web',
        ]);

        foreach (range(1, 5) as $ignored) {
            $attempt($user->correo, 'incorrecta')->assertUnprocessable();
        }

        $attempt($user->correo, 'incorrecta')->assertTooManyRequests()
            ->assertJsonPath('message', fn (string $message) => str_starts_with($message, 'Demasiados intentos.'));

        $attempt($other->correo, 'password')->assertOk();
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

    public function test_student_can_login_with_valid_credentials_and_receive_student_role(): void
    {
        $student = Usuario::factory()
            ->withRole('estudiante')
            ->create([
                'password_hash' => 'password123',
                'estado' => true,
            ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $student->correo,
            'password' => 'password123',
            'device_name' => 'estudiante-web',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.role', 'estudiante')
            ->assertJsonPath('data.user.email', $student->correo)
            ->assertJsonStructure(['data' => ['access_token', 'expires_at']]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $student->getKey(),
            'name' => 'estudiante-web',
        ]);
    }

    public function test_student_receives_generic_error_on_invalid_credentials(): void
    {
        $student = Usuario::factory()
            ->withRole('estudiante')
            ->create([
                'password_hash' => 'password123',
                'estado' => true,
            ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $student->correo,
            'password' => 'clave_erronea',
            'device_name' => 'estudiante-web',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath('errors.email.0', 'Las credenciales proporcionadas no son correctas.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_inactive_student_cannot_login(): void
    {
        $student = Usuario::factory()
            ->withRole('estudiante')
            ->create([
                'password_hash' => 'password123',
                'estado' => false,
            ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $student->correo,
            'password' => 'password123',
            'device_name' => 'estudiante-web',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath('errors.email.0', 'Las credenciales proporcionadas no son correctas.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_student_can_logout_and_token_is_invalidated(): void
    {
        $student = Usuario::factory()
            ->withRole('estudiante')
            ->create(['estado' => true]);

        $token = $student->createToken('estudiante-web')->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$token];

        $response = $this->withHeaders($headers)->deleteJson('/api/v1/auth/logout');
        $response->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $student->getKey(),
        ]);
        $this->assertNull(PersonalAccessToken::findToken($token));

        $this->app['auth']->forgetGuards();
        $this->withHeaders($headers)->getJson('/api/v1/auth/user')->assertUnauthorized();
    }

    public function test_student_can_access_student_degree_topics_endpoint(): void
    {
        $student = Usuario::factory()
            ->withRole('estudiante')
            ->create(['estado' => true]);

        $token = $student->createToken('estudiante-web')->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$token];

        $response = $this->withHeaders($headers)->getJson('/api/v1/student/degree-topics');
        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_student_cannot_access_administrative_endpoints(): void
    {
        $student = Usuario::factory()
            ->withRole('estudiante')
            ->create(['estado' => true]);

        $token = $student->createToken('estudiante-web')->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$token];

        $this->withHeaders($headers)->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_student_cannot_access_coordination_endpoints(): void
    {
        $student = Usuario::factory()
            ->withRole('estudiante')
            ->create(['estado' => true]);

        $token = $student->createToken('estudiante-web')->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$token];

        $this->withHeaders($headers)->getJson('/api/v1/coordination/teachers')->assertForbidden();
    }

    public function test_unauthenticated_user_request_without_accept_header_returns_401(): void
    {
        $response = $this->get('/api/v1/auth/user');
        $response->assertStatus(401);
        $response->assertJson(['message' => 'No autenticado.']);
    }

    public function test_responses_include_hsts_and_security_headers(): void
    {
        $response = $this->postJson('/api/v1/auth/login');
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_not_found_error_does_not_leak_eloquent_model_name(): void
    {
        $admin = Usuario::factory()->withRole('administrador')->create();

        $token = $admin->createToken('admin-web')->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$token];

        $response = $this->withHeaders($headers)->patchJson('/api/v1/faculties/999999', ['name' => 'X']);
        $response->assertNotFound();
        $response->assertJson(['message' => 'Recurso no encontrado.']);
        $this->assertStringNotContainsString('App\\Models', $response->getContent());
    }

    public function test_health_check_endpoint_is_blocked_for_external_public_ips(): void
    {
        $response = $this->withServerVariables(['REMOTE_ADDR' => '201.218.10.5'])->get('/up');
        $response->assertNotFound();
    }

    public function test_health_check_endpoint_is_allowed_for_internal_ips(): void
    {
        $response = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->get('/up');
        $response->assertOk();
    }
}

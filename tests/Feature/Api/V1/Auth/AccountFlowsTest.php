<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\Usuario;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Fortify;
use Tests\TestCase;

class AccountFlowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receives_a_verification_notification(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'identification' => '0201234567',
            'name' => 'Ana Torres',
            'email' => 'ana@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'device_name' => 'frontend-web',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.email', 'ana@example.com')
            ->assertJsonStructure(['data' => ['access_token']]);

        $user = Usuario::query()->where('correo', 'ana@example.com')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_password_reset_request_does_not_reveal_whether_email_exists(): void
    {
        Notification::fake();
        $user = Usuario::factory()->create();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->correo])
            ->assertAccepted();
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'unknown@example.com'])
            ->assertAccepted();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_user_can_reset_password_and_existing_tokens_are_revoked(): void
    {
        $user = Usuario::factory()->create();
        $user->createToken('frontend-web');
        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->correo,
            'token' => $token,
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])->assertOk();

        $this->assertTrue(password_verify('NewPassword1!', $user->fresh()->password_hash));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_authenticated_user_can_verify_email_with_a_signed_link(): void
    {
        $user = Usuario::factory()->unverified()->create();
        $url = URL::temporarySignedRoute(
            'api.v1.auth.verification.verify',
            now()->addHour(),
            ['id' => $user->getKey(), 'hash' => sha1($user->correo)],
        );

        $this->getJson($url)->assertOk();

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_two_factor_recovery_code_exchanges_challenge_for_session_token(): void
    {
        $user = Usuario::factory()->create([
            'password_hash' => 'password',
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt('secret'),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(['recovery-code'])),
            'two_factor_confirmed_at' => now(),
        ]);

        $challengeToken = $this->postJson('/api/v1/auth/login', [
            'email' => $user->correo,
            'password' => 'password',
            'device_name' => 'frontend-web',
        ])->json('data.challenge_token');

        $response = $this->withToken($challengeToken)->postJson('/api/v1/auth/two-factor-challenge', [
            'recovery_code' => 'recovery-code',
        ]);

        $response->assertOk()->assertJsonStructure(['data' => ['access_token']]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->getKey(),
            'name' => 'two-factor-challenge:frontend-web',
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->getKey(),
            'name' => 'frontend-web',
        ]);
    }
}

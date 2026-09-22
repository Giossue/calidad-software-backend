<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Usuario;
use App\Notifications\ProvisionalPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Usuario::factory()->create(['rol' => 'administrador']);
        Sanctum::actingAs($this->admin);
    }

    public function test_admin_can_list_active_and_deactivated_users(): void
    {
        $active = Usuario::factory()->create(['nombre' => 'Ana Activa']);
        $inactive = Usuario::factory()->create(['nombre' => 'Bruno Inactivo', 'estado' => false]);

        $this->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonPath('data.0.id', $active->getKey())
            ->assertJsonPath('data.0.is_active', true)
            ->assertJsonPath('data.1.id', $inactive->getKey())
            ->assertJsonPath('data.1.is_active', false);
    }

    public function test_non_administrator_cannot_list_users(): void
    {
        Sanctum::actingAs(Usuario::factory()->create(['rol' => 'docente']));

        $this->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_admin_can_register_a_user(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/users', [
            'identification' => '0102030405',
            'name' => 'Luis Pérez',
            'email' => 'luis@example.com',
            'phone' => '0991234567',
            'role' => 'estudiante',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.identification', '0102030405')
            ->assertJsonPath('data.email', 'luis@example.com')
            ->assertJsonPath('data.role', 'estudiante')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('message', 'Usuario creado. Revisa el correo registrado para obtener la contraseña provisional.');

        $this->assertDatabaseHas('usuario', [
            'cedula' => '0102030405',
            'correo' => 'luis@example.com',
            'telefono' => '0991234567',
            'rol' => 'estudiante',
            'estado' => true,
        ]);

        $user = Usuario::query()->where('correo', 'luis@example.com')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        Notification::assertSentTo($user, ProvisionalPasswordNotification::class, function (ProvisionalPasswordNotification $notification) use ($user): bool {
            return password_verify($notification->provisionalPassword, $user->password_hash);
        });
    }

    public function test_registering_a_user_rejects_duplicate_cedula_and_email(): void
    {
        Usuario::factory()->create(['cedula' => '0102030405', 'correo' => 'existente@example.com']);

        $response = $this->postJson('/api/v1/users', [
            'identification' => '0102030405',
            'name' => 'Luis Pérez',
            'email' => 'existente@example.com',
            'role' => 'estudiante',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['identification', 'email']);
    }

    public function test_registering_a_user_rejects_an_invalid_role(): void
    {
        $this->postJson('/api/v1/users', [
            'identification' => '0102030405',
            'name' => 'Luis Pérez',
            'email' => 'luis@example.com',
            'role' => 'lider',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    public function test_admin_can_update_a_user(): void
    {
        $user = Usuario::factory()->create();

        $response = $this->patchJson("/api/v1/users/{$user->getKey()}", [
            'name' => 'Nombre Actualizado',
            'email' => 'nuevo@example.com',
            'role' => 'docente',
            'phone' => '0987654321',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Nombre Actualizado')
            ->assertJsonPath('data.email', 'nuevo@example.com')
            ->assertJsonPath('data.role', 'docente')
            ->assertJsonPath('data.phone', '0987654321');

        $this->assertDatabaseHas('usuario', [
            'id_usuario' => $user->getKey(),
            'nombre' => 'Nombre Actualizado',
            'correo' => 'nuevo@example.com',
            'telefono' => '0987654321',
            'rol' => 'docente',
        ]);
    }

    public function test_update_without_password_keeps_the_existing_hash(): void
    {
        $user = Usuario::factory()->create();

        $this->patchJson("/api/v1/users/{$user->getKey()}", ['name' => 'Sin Clave'])
            ->assertOk();

        $this->assertSame(
            $user->fresh()->password_hash,
            $user->password_hash,
        );
        $this->assertTrue(password_verify('password', $user->fresh()->password_hash));
    }

    public function test_update_with_password_rehashes_and_preserves_other_fields(): void
    {
        $user = Usuario::factory()->create(['cedula' => '0302010405']);

        $this->patchJson("/api/v1/users/{$user->getKey()}", [
            'password' => 'NuevaClave1!',
            'password_confirmation' => 'NuevaClave1!',
        ])->assertOk();

        $fresh = $user->fresh();
        $this->assertNotSame($user->password_hash, $fresh->password_hash);
        $this->assertTrue(password_verify('NuevaClave1!', $fresh->password_hash));
        $this->assertSame('0302010405', $fresh->cedula);
    }

    public function test_update_rejects_cedula_or_email_used_by_another_user(): void
    {
        $other = Usuario::factory()->create();
        $user = Usuario::factory()->create();

        $this->patchJson("/api/v1/users/{$user->getKey()}", [
            'cedula2' => null,
            'identification' => $other->cedula,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['identification']);

        $this->patchJson("/api/v1/users/{$user->getKey()}", ['email' => $other->correo])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_admin_can_deactivate_a_user_without_deleting_the_record(): void
    {
        $user = Usuario::factory()->create();

        $response = $this->patchJson("/api/v1/users/{$user->getKey()}/deactivate");

        $response->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('usuario', [
            'id_usuario' => $user->getKey(),
            'estado' => false,
        ]);
        $this->assertDatabaseCount('usuario', 2);
    }

    public function test_deactivated_user_cannot_login(): void
    {
        $user = Usuario::factory()->create();

        $this->patchJson("/api/v1/users/{$user->getKey()}/deactivate")->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->correo,
            'password' => 'password',
            'device_name' => 'test-device',
        ])->assertUnprocessable();
    }

    public function test_user_and_faculty_endpoints_require_authentication(): void
    {
        $this->app->make('auth')->forgetGuards();

        $this->postJson('/api/v1/users', [])->assertUnauthorized();
        $this->postJson('/api/v1/faculties', [])->assertUnauthorized();
    }
}

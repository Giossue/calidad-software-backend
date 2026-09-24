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

        $this->admin = Usuario::factory()->withRole('administrador')->create(['nombre' => 'Zzz Admin']);
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
        Sanctum::actingAs(Usuario::factory()->withRole('docente')->create());

        $this->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_admin_can_register_a_user(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/users', [
            'identification' => '0926687856',
            'name' => 'Luis Pérez',
            'email' => 'luis@example.com',
            'phone' => '0991234567',
            'role' => 'estudiante',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.identification', '0926687856')
            ->assertJsonPath('data.email', 'luis@example.com')
            ->assertJsonPath('data.role', 'estudiante')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('message', 'Usuario creado. Revisa el correo registrado para obtener la contraseña provisional.');

        $this->assertDatabaseHas('usuario', [
            'cedula' => '0926687856',
            'correo' => 'luis@example.com',
            'telefono' => '0991234567',
            'estado' => true,
        ]);

        $user = Usuario::query()->where('correo', 'luis@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('estudiante'));
        $this->assertNotNull($user->email_verified_at);
        Notification::assertSentTo($user, ProvisionalPasswordNotification::class, function (ProvisionalPasswordNotification $notification) use ($user): bool {
            return password_verify($notification->provisionalPassword, $user->password_hash);
        });
    }

    public function test_registering_a_user_rejects_duplicate_cedula_and_email(): void
    {
        Usuario::factory()->create(['cedula' => '0926687856', 'correo' => 'existente@example.com']);

        $response = $this->postJson('/api/v1/users', [
            'identification' => '0926687856',
            'name' => 'Luis Pérez',
            'email' => 'existente@example.com',
            'phone' => '0991234567',
            'role' => 'estudiante',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['identification', 'email']);
    }

    public function test_registering_a_user_rejects_an_invalid_role(): void
    {
        $this->postJson('/api/v1/users', [
            'identification' => '0926687856',
            'name' => 'Luis Pérez',
            'email' => 'luis@example.com',
            'phone' => '0991234567',
            'role' => 'lider',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    public function test_registering_a_user_rejects_an_invalid_cedula_checksum(): void
    {
        $this->postJson('/api/v1/users', [
            'identification' => '0102030405',
            'name' => 'Luis Pérez',
            'email' => 'luis@example.com',
            'phone' => '0991234567',
            'role' => 'estudiante',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['identification']);
    }

    public function test_registering_a_user_requires_a_ten_digit_phone(): void
    {
        $this->postJson('/api/v1/users', [
            'identification' => '0926687856',
            'name' => 'Luis Pérez',
            'email' => 'luis@example.com',
            'phone' => '099123',
            'role' => 'estudiante',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);

        $this->postJson('/api/v1/users', [
            'identification' => '0926687856',
            'name' => 'Luis Pérez',
            'email' => 'luis@example.com',
            'role' => 'estudiante',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_registering_a_user_rejects_a_name_with_digits_or_symbols(): void
    {
        $this->postJson('/api/v1/users', [
            'identification' => '0926687856',
            'name' => 'Luis P3rez!',
            'email' => 'luis@example.com',
            'phone' => '0991234567',
            'role' => 'estudiante',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
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
        ]);
        $this->assertTrue($user->fresh()->hasRole('docente'));
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
        $user = Usuario::factory()->create(['cedula' => '0320104052']);

        $this->patchJson("/api/v1/users/{$user->getKey()}", [
            'password' => 'NuevaClave1!',
            'password_confirmation' => 'NuevaClave1!',
        ])->assertOk();

        $fresh = $user->fresh();
        $this->assertNotSame($user->password_hash, $fresh->password_hash);
        $this->assertTrue(password_verify('NuevaClave1!', $fresh->password_hash));
        $this->assertSame('0320104052', $fresh->cedula);
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

    public function test_admin_can_reactivate_a_deactivated_user(): void
    {
        $user = Usuario::factory()->create(['estado' => false]);

        $response = $this->patchJson("/api/v1/users/{$user->getKey()}/activate");

        $response->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('usuario', [
            'id_usuario' => $user->getKey(),
            'estado' => true,
        ]);
    }

    public function test_user_index_paginates_and_reports_catalog_wide_counts(): void
    {
        Usuario::factory()->create(['nombre' => 'Usuario Activo Uno']);
        Usuario::factory()->create(['nombre' => 'Usuario Activo Dos']);
        Usuario::factory()->create(['nombre' => 'Usuario Inactivo', 'estado' => false]);

        $response = $this->getJson('/api/v1/users?per_page=2');

        $response->assertOk()->assertJsonPath('meta.per_page', 2);
        // +1 porque el administrador de setUp() también cuenta.
        $this->assertSame(3, $response->json('meta.active_count'));
        $this->assertSame(1, $response->json('meta.inactive_count'));
        $this->assertSame(1, $response->json('meta.admin_count'));
        $this->assertSame(3, $response->json('meta.student_count'));
        $this->assertSame(0, $response->json('meta.teacher_count'));
        $this->assertCount(2, $response->json('data'));
    }

    public function test_user_index_filters_by_search_and_role(): void
    {
        Usuario::factory()->withRole('estudiante')->create(['nombre' => 'Laura Estudiante', 'correo' => 'laura@example.com']);
        Usuario::factory()->withRole('docente')->create(['nombre' => 'Marco Docente', 'correo' => 'marco@example.com']);

        $bySearch = $this->getJson('/api/v1/users?search=laura');
        $names = collect($bySearch->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Laura Estudiante'));
        $this->assertFalse($names->contains('Marco Docente'));

        $byRole = $this->getJson('/api/v1/users?role=docente');
        $roles = collect($byRole->json('data'))->pluck('role');
        $this->assertTrue($roles->every(fn (string $role) => $role === 'docente'));
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

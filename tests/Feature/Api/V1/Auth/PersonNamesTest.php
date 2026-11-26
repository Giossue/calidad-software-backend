<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Nombres y apellidos se registran en dos campos y se guardan como "apellidos nombres". */
class PersonNamesTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_completion_stores_last_names_then_first_names(): void
    {
        $user = Usuario::factory()->withRole('docente')->create(['cedula' => null]);

        $this->complete($user, ['first_names' => '  Ana   María ', 'last_names' => 'Torres  Vera'])
            ->assertOk()
            ->assertJsonPath('data.name', 'TORRES VERA ANA MARÍA');

        $this->assertSame('TORRES VERA ANA MARÍA', $user->refresh()->nombre);
    }

    public function test_first_names_require_one_or_two_names(): void
    {
        $user = Usuario::factory()->withRole('docente')->create(['cedula' => null]);

        $this->complete($user, ['first_names' => 'Juan Carlos Andrés', 'last_names' => 'Mora Vera'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.first_names.0', 'Ingresa uno o dos nombres.');
        $this->complete($user, ['first_names' => '   ', 'last_names' => 'Mora Vera'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_names']);
        $this->assertNotNull($user->refresh()->nombre);
        $this->assertNull($user->cedula);
    }

    public function test_last_names_allow_two_or_exceptionally_one(): void
    {
        $user = Usuario::factory()->withRole('docente')->create(['cedula' => null]);

        $this->complete($user, ['first_names' => 'Luis', 'last_names' => 'Mora Vera Paz'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.last_names.0', 'Ingresa tus dos apellidos, o uno solo si tu registro civil tiene un único apellido.');
        $this->complete($user, ['first_names' => 'Luis', 'last_names' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['last_names']);

        $this->complete($user, ['first_names' => 'John', 'last_names' => 'Smith'])->assertOk();
        $this->assertSame('SMITH JOHN', $user->refresh()->nombre);
    }

    public function test_connecting_particles_are_part_of_a_name(): void
    {
        $user = Usuario::factory()->withRole('docente')->create(['cedula' => null]);

        $this->complete($user, ['first_names' => 'María de los Ángeles', 'last_names' => 'De la Torre Vera'])->assertOk();

        $this->assertSame('DE LA TORRE VERA MARÍA DE LOS ÁNGELES', $user->refresh()->nombre);
    }

    public function test_names_only_accept_letters_and_spaces(): void
    {
        $user = Usuario::factory()->withRole('docente')->create(['cedula' => null]);

        $this->complete($user, ['first_names' => 'Ana1', 'last_names' => 'Torres-Vera'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_names', 'last_names']);
    }

    public function test_registration_takes_first_and_last_names(): void
    {
        config(['auth.registration_enabled' => true]);
        Notification::fake();

        $this->postJson('/api/v1/auth/register', [
            'identification' => '0201234567',
            'first_names' => 'Ana',
            'last_names' => 'Torres Vera',
            'email' => 'ana@ueb.edu.ec',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'device_name' => 'frontend-web',
        ])->assertCreated();

        $this->assertSame('TORRES VERA ANA', Usuario::query()->where('correo', 'ana@ueb.edu.ec')->value('nombre'));

        $this->postJson('/api/v1/auth/register', [
            'identification' => '0201234568',
            'first_names' => 'Luis Mario José',
            'last_names' => 'Mora',
            'email' => 'luis@ueb.edu.ec',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'device_name' => 'frontend-web',
        ])->assertUnprocessable()->assertJsonValidationErrors(['first_names']);
    }

    /** @param  array<string, string>  $names */
    private function complete(Usuario $user, array $names): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('web', ['*'])->plainTextToken)
            ->putJson('/api/v1/auth/profile/complete', [
                'identification' => '0926687856', 'phone' => '0991234567', ...$names,
            ]);
    }
}

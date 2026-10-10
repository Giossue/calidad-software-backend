<?php

namespace Tests\Feature\Api\V1\Imports;

use App\Models\Usuario;
use App\Notifications\QueuedProvisionalPasswordNotification;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Api\V1\Tutoring\TutoringTestCase;

class UserImportTest extends TutoringTestCase
{
    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Usuario::factory()->withRole('administrador')->create();
        Notification::fake();
    }

    public function test_admin_imports_users_with_only_email_role_and_career(): void
    {
        Usuario::factory()->create(['correo' => 'existente@ueb.edu.ec']);
        Sanctum::actingAs($this->admin, ['*']);

        $csv = "correo;rol;carrera;cedula;nombre;telefono\n"
            ."Ana.Torres@ueb.edu.ec;estudiante;software;;;\n"
            ."luis@ueb.edu.ec;Docente;Software;926687856;Luis Pérez;991234567\n"
            ."jefa@ueb.edu.ec;administrador;;;;\n"
            ."x@ueb.edu.ec;rector;Software;;;\n"
            ."existente@ueb.edu.ec;estudiante;Software;;;\n";
        $id = $this->upload('users', $csv)->json('data.id');

        $this->getJson('/api/v1/imports/'.$id)
            ->assertJsonPath('data.created_count', 3)
            ->assertJsonPath('data.errors.0.row', 5)
            ->assertJsonPath('data.errors.1.row', 6)
            ->assertJsonPath('data.errors.1.messages.0', 'El correo existente@ueb.edu.ec ya está registrado.');

        $student = Usuario::query()->where('correo', 'ana.torres@ueb.edu.ec')->firstOrFail();
        $this->assertNull($student->cedula);
        $this->assertSame('Ana Torres', $student->nombre);
        $this->assertTrue($student->must_complete_profile);
        $this->assertTrue($student->hasRole('estudiante'));
        $this->assertSame($this->career->getKey(), $student->fk_carrera);
        $this->assertNotNull($student->email_verified_at);

        $teacher = Usuario::query()->where('correo', 'luis@ueb.edu.ec')->firstOrFail();
        $this->assertSame('0926687856', $teacher->cedula);
        $this->assertSame('0991234567', $teacher->telefono);
        $this->assertTrue($teacher->teachingCareers()->whereKey($this->career->getKey())->exists());

        Notification::assertSentTo($student, QueuedProvisionalPasswordNotification::class,
            fn (QueuedProvisionalPasswordNotification $notification): bool => password_verify($notification->provisionalPassword, $student->password_hash));
        Notification::assertCount(3);
    }

    public function test_provisional_password_notification_is_queued_and_encrypted(): void
    {
        $notification = new QueuedProvisionalPasswordNotification('secreta');

        $this->assertInstanceOf(ShouldQueue::class, $notification);
        $this->assertInstanceOf(ShouldBeEncrypted::class, $notification);
        $this->assertStringContainsString('completar tus datos', implode(' ', $notification->toMail(Usuario::factory()->make(['must_complete_profile' => true]))->introLines));
    }

    public function test_coordinator_imports_teachers_and_students_only_with_institutional_email_in_own_careers(): void
    {
        Sanctum::actingAs($this->coordinator, ['*']);

        $id = $this->upload('teachers', "correo,carrera\ndocente1@ueb.edu.ec,Software\ndocente2@gmail.com,Software\ndocente3@ueb.edu.ec,Administración\n")->json('data.id');
        $this->getJson('/api/v1/imports/'.$id)
            ->assertJsonPath('data.created_count', 1)
            ->assertJsonPath('data.errors.0.messages.0', 'El correo debe ser institucional (@ueb.edu.ec).')
            ->assertJsonPath('data.errors.1.messages.0', 'No tienes permiso para registrar este elemento.');
        $this->assertTrue(Usuario::query()->where('correo', 'docente1@ueb.edu.ec')->firstOrFail()->hasRole('docente'));

        $this->upload('students', "correo,carrera\nalumno@ueb.edu.ec,Software\n")->assertAccepted();
        $this->assertTrue(Usuario::query()->where('correo', 'alumno@ueb.edu.ec')->firstOrFail()->hasRole('estudiante'));

        $this->upload('users', "correo,rol,carrera\nx@ueb.edu.ec,estudiante,Software\n")->assertForbidden();
    }

    public function test_incomplete_profile_blocks_the_api_until_data_and_password_are_completed(): void
    {
        $user = Usuario::factory()->withRole('estudiante')->create(['cedula' => null, 'telefono' => null, 'must_complete_profile' => true]);
        $other = $user->createToken('otro', ['*'])->plainTextToken;
        $token = $user->createToken('web', ['*'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/student/degree-enrollment-status')
            ->assertForbidden()
            ->assertJsonPath('code', 'profile_incomplete');
        $this->withToken($token)->getJson('/api/v1/auth/user')
            ->assertOk()
            ->assertJsonPath('data.must_complete_profile', true)
            ->assertJsonPath('data.identification', null);

        $this->withToken($token)->putJson('/api/v1/auth/profile/complete', [
            'identification' => '0926687856', 'name' => 'Ana Torres', 'phone' => '0991234567',
            'password' => 'password', 'password_confirmation' => 'password',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);

        $this->withToken($token)->putJson('/api/v1/auth/profile/complete', [
            'identification' => 'ab1234567', 'name' => 'Ana Torres', 'phone' => '0991234567',
            'password' => 'Nueva-Clave-2026', 'password_confirmation' => 'Nueva-Clave-2026',
        ])->assertOk()
            ->assertJsonPath('data.must_complete_profile', false)
            ->assertJsonPath('data.identification', 'AB1234567');

        $user->refresh();
        $this->assertSame('Ana Torres', $user->nombre);
        $this->assertTrue(password_verify('Nueva-Clave-2026', $user->password_hash));
        $this->assertSame(1, $user->tokens()->count());
        $this->assertNotSame(explode('|', $other)[0], (string) $user->tokens()->value('id'));

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/student/degree-enrollment-status')->assertOk();
        $this->withToken($token)->putJson('/api/v1/auth/profile/complete', [])->assertForbidden();
    }

    public function test_an_account_without_identification_must_register_it_without_changing_its_password(): void
    {
        $user = Usuario::factory()->withRole('administrador')->create(['cedula' => null, 'must_complete_profile' => false, 'password_hash' => 'Clave-Actual-2026']);
        $token = $user->createToken('web', ['*'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/users')
            ->assertForbidden()
            ->assertJsonPath('code', 'profile_incomplete');
        $this->withToken($token)->getJson('/api/v1/auth/user')
            ->assertOk()
            ->assertJsonPath('data.must_complete_profile', true)
            ->assertJsonPath('data.must_change_password', false);

        $this->withToken($token)->putJson('/api/v1/auth/profile/complete', [
            'identification' => '0926687856', 'name' => 'Ana Torres', 'phone' => '0991234567',
        ])->assertOk()
            ->assertJsonPath('data.must_complete_profile', false)
            ->assertJsonPath('data.identification', '0926687856');

        $this->assertTrue(password_verify('Clave-Actual-2026', $user->refresh()->password_hash));
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/users')->assertOk();
    }

    private function upload(string $type, string $contents): TestResponse
    {
        return $this->post('/api/v1/imports/'.$type, [
            'file' => UploadedFile::fake()->createWithContent('datos.csv', $contents),
        ], ['Accept' => 'application/json']);
    }
}

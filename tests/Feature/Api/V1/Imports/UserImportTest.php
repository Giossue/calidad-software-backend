<?php

namespace Tests\Feature\Api\V1\Imports;

use App\Models\Usuario;
use App\Notifications\ActionNotification;
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

    public function test_admin_imports_users_with_names_faculty_career_and_cycle(): void
    {
        Usuario::factory()->create(['correo' => 'existente@ueb.edu.ec']);
        Sanctum::actingAs($this->admin, ['*']);

        $csv = "Correo;Cédula;Nombres;Apellidos;Rol;Facultad;Carrera;Ciclo;Teléfono\n"
            ."Ana.Torres@ueb.edu.ec;;;;estudiante;ciencias;software;1;\n"
            ."luis@ueb.edu.ec;926687856;Luis Alberto;Pérez  Mora;Docente;Ciencias;Software;;991234567\n"
            ."jefa@ueb.edu.ec;;;;administrador;;;;\n"
            ."x@ueb.edu.ec;;;;rector;Ciencias;Software;;\n"
            ."existente@ueb.edu.ec;;;;estudiante;Ciencias;Software;1;\n"
            ."sin.ciclo@ueb.edu.ec;;;;estudiante;Ciencias;Software;;\n"
            ."otra.facultad@ueb.edu.ec;;;;docente;Medicina;Software;;\n"
            ."ciclo.9@ueb.edu.ec;;;;estudiante;Ciencias;Software;9;\n";
        $id = $this->upload('users', $csv)->json('data.id');

        $this->getJson('/api/v1/imports/'.$id)
            ->assertJsonPath('data.created_count', 3)
            ->assertJsonPath('data.failed_count', 4)
            ->assertJsonPath('data.errors.0.row', 5)
            ->assertJsonPath('data.errors.1.messages.0', 'El ciclo es obligatorio para los estudiantes.')
            ->assertJsonPath('data.errors.2.messages.0', 'La facultad «Medicina» no existe o está inactiva.')
            ->assertJsonPath('data.errors.3.messages.0', 'El ciclo «9» no existe en la carrera indicada.')
            ->assertJsonPath('data.skipped_count', 1)
            ->assertJsonPath('data.warnings.0.row', 6)
            ->assertJsonPath('data.warnings.0.messages.0', 'El correo existente@ueb.edu.ec ya está registrado.');
        Notification::assertSentTo($this->admin, ActionNotification::class,
            fn (ActionNotification $notification): bool => $notification->body === '3 registros creados, 1 ya estaba registrado y 4 con errores.' && $notification->url === '/panel/users');

        $student = Usuario::query()->where('correo', 'ana.torres@ueb.edu.ec')->firstOrFail();
        $this->assertNull($student->cedula);
        $this->assertSame('Ana Torres', $student->nombre);
        $this->assertTrue($student->must_complete_profile);
        $this->assertTrue($student->hasRole('estudiante'));
        $this->assertSame([$this->career->getKey(), $this->cycle->getKey()], [$student->fk_carrera, $student->fk_ciclo]);
        $this->assertTrue($student->paralelos()->whereKey($this->cycle->fk_paralelo)->exists());
        $this->assertNotNull($student->email_verified_at);

        $teacher = Usuario::query()->where('correo', 'luis@ueb.edu.ec')->firstOrFail();
        $this->assertSame('Pérez Mora Luis Alberto', $teacher->nombre);
        $this->assertSame('0926687856', $teacher->cedula);
        $this->assertSame('0991234567', $teacher->telefono);
        $this->assertNull($teacher->fk_ciclo);
        $this->assertTrue($teacher->teachingCareers()->whereKey($this->career->getKey())->exists());

        Notification::assertSentTo($student, QueuedProvisionalPasswordNotification::class,
            fn (QueuedProvisionalPasswordNotification $notification): bool => password_verify($notification->provisionalPassword, $student->password_hash));
        Notification::assertCount(4); // 3 contraseñas provisionales + el aviso de carga masiva terminada
    }

    public function test_account_template_lists_the_shared_columns(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $this->get('/api/v1/imports/users/template')->assertOk()->assertSee('correo;cedula;nombres;apellidos;rol;facultad;carrera;ciclo;telefono', false);
        $this->get('/api/v1/imports/students/template')->assertOk()->assertSee('estudiante;"Ciencias Administrativas";Software;1;0991234567', false);
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

        $id = $this->upload('teachers', "correo,facultad,carrera\ndocente1@ueb.edu.ec,Ciencias,Software\ndocente2@gmail.com,Ciencias,Software\ndocente3@ueb.edu.ec,Ciencias,Administración\n")->json('data.id');
        $this->getJson('/api/v1/imports/'.$id)
            ->assertJsonPath('data.created_count', 1)
            ->assertJsonPath('data.errors.0.messages.0', 'El correo debe ser institucional (@ueb.edu.ec).')
            ->assertJsonPath('data.errors.1.messages.0', 'No tienes permiso para registrar este elemento.');
        $this->assertTrue(Usuario::query()->where('correo', 'docente1@ueb.edu.ec')->firstOrFail()->hasRole('docente'));

        $this->upload('students', "correo,carrera\nalumno@ueb.edu.ec,Software\n")
            ->assertUnprocessable()->assertJsonValidationErrors(['file' => 'Faltan columnas obligatorias en el CSV: facultad, ciclo.']);
        $id = $this->upload('students', "correo,nombres,apellidos,rol,facultad,carrera,ciclo\nalumno@ueb.edu.ec,Rosa,Vera,estudiante,Ciencias,Software,1.º\notro@ueb.edu.ec,,,docente,Ciencias,Software,1\n")->json('data.id');
        $this->getJson('/api/v1/imports/'.$id)
            ->assertJsonPath('data.created_count', 1)
            ->assertJsonPath('data.errors.0.messages.0', 'En esta carga todas las cuentas son de rol estudiante.');
        $student = Usuario::query()->where('correo', 'alumno@ueb.edu.ec')->firstOrFail();
        $this->assertTrue($student->hasRole('estudiante'));
        $this->assertSame(['Vera Rosa', $this->cycle->getKey()], [$student->nombre, $student->fk_ciclo]);

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

        $profile = [
            'identification' => 'ab1234567', 'name' => 'Ana Torres', 'phone' => '0991234567',
            'password' => 'Nueva-Clave-2026', 'password_confirmation' => 'Nueva-Clave-2026',
        ];
        $this->withToken($token)->putJson('/api/v1/auth/profile/complete', $profile)
            ->assertUnprocessable()->assertJsonValidationErrors(['faculty_id', 'career_id', 'cycle_id']);

        $this->withToken($token)->putJson('/api/v1/auth/profile/complete', [
            ...$profile,
            'faculty_id' => $this->career->fk_facultad, 'career_id' => $this->career->getKey(), 'cycle_id' => $this->cycle->getKey(),
        ])->assertOk()
            ->assertJsonPath('data.must_complete_profile', false)
            ->assertJsonPath('data.identification', 'AB1234567')
            ->assertJsonPath('data.career_id', $this->career->getKey())
            ->assertJsonPath('data.cycle_id', $this->cycle->getKey());

        $user->refresh();
        $this->assertSame('Ana Torres', $user->nombre);
        $this->assertSame($this->cycle->getKey(), $user->fk_ciclo);
        $this->assertTrue($user->paralelos()->whereKey($this->cycle->fk_paralelo)->exists());
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

    public function test_a_student_without_career_or_cycle_must_register_them_and_the_submitted_data_replaces_the_old_one(): void
    {
        $user = Usuario::factory()->withRole('estudiante')->create(['fk_carrera' => $this->otherCareer->getKey(), 'password_hash' => 'Clave-Actual-2026']);
        $token = $user->createToken('web', ['*'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/student/degree-enrollment-status')
            ->assertForbidden()->assertJsonPath('code', 'profile_incomplete');
        $this->withToken($token)->getJson('/api/v1/auth/user')
            ->assertOk()
            ->assertJsonPath('data.must_complete_profile', true)
            ->assertJsonPath('data.must_change_password', false);

        $options = $this->withToken($token)->getJson('/api/v1/auth/profile/academic-options')->assertOk()->json('data');
        $careers = collect($options)->flatMap(fn (array $faculty) => $faculty['careers']);
        $this->assertContains($this->cycle->getKey(), array_column($careers->firstWhere('id', $this->career->getKey())['cycles'], 'id'));

        $payload = ['identification' => $user->cedula, 'name' => 'Luis Mora', 'phone' => '0987654321', 'faculty_id' => $this->career->fk_facultad];
        // El ciclo debe pertenecer a la carrera elegida.
        $this->withToken($token)->putJson('/api/v1/auth/profile/complete', [
            ...$payload, 'career_id' => $this->otherCareer->getKey(), 'cycle_id' => $this->cycle->getKey(),
        ])->assertUnprocessable()->assertJsonValidationErrors(['cycle_id']);

        $this->withToken($token)->putJson('/api/v1/auth/profile/complete', [
            ...$payload, 'career_id' => $this->career->getKey(), 'cycle_id' => $this->cycle->getKey(),
        ])->assertOk()
            ->assertJsonPath('data.must_complete_profile', false)
            ->assertJsonPath('data.name', 'Luis Mora')
            ->assertJsonPath('data.career_id', $this->career->getKey())
            ->assertJsonPath('data.cycle_number', $this->cycle->numero)
            ->assertJsonPath('data.academic_stage', 'tutorias');

        $user->refresh();
        $this->assertSame([$this->career->getKey(), $this->cycle->getKey(), '0987654321'], [$user->fk_carrera, $user->fk_ciclo, $user->telefono]);
        $this->assertTrue(password_verify('Clave-Actual-2026', $user->password_hash));
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/student/degree-enrollment-status')->assertOk();
    }

    public function test_only_students_are_asked_for_faculty_career_and_cycle(): void
    {
        $teacher = Usuario::factory()->withRole('docente')->create(['cedula' => null]);
        $token = $teacher->createToken('web', ['*'])->plainTextToken;

        $this->withToken($token)->putJson('/api/v1/auth/profile/complete', [
            'identification' => '0926687856', 'name' => 'Rosa Vera', 'phone' => '0991234567',
        ])->assertOk()->assertJsonPath('data.must_complete_profile', false)->assertJsonMissingPath('data.cycle_id');
        $this->assertNull($teacher->refresh()->fk_ciclo);
    }
}

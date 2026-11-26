<?php

namespace Tests\Feature\Api\V1\Tutoring;

use App\Actions\Teacher\TutoringFollowUpReport;
use App\Models\AsignaturaTutoria;
use App\Models\Facultad;
use App\Models\InscripcionTutoria;
use App\Models\Malla;
use App\Models\PeriodoAcademico;
use App\Models\Reporte;
use App\Models\Subject;
use App\Models\Usuario;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

/**
 * El coordinador trabaja con una malla a la vez y cada estudiante sigue la suya:
 * tutorías, estudiantes y reportes se filtran por malla, y nadie se inscribe en
 * tutorías de otra malla.
 */
class MallaStudentsTest extends TutoringTestCase
{
    private Malla $current;

    private Malla $old;

    private Subject $oldSubject;

    private AsignaturaTutoria $currentTutoring;

    private AsignaturaTutoria $oldTutoring;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->current = $this->currentMalla();
        $this->old = Malla::query()->create(['career_id' => $this->career->getKey(), 'name' => 'Malla 2016', 'is_current' => false]);
        $this->currentTutoring = $this->createTutoring();
        $this->oldSubject = Subject::query()->create([
            'malla_id' => $this->old->getKey(), 'code' => 'OLD-101', 'name' => 'CALIDAD ANTIGUA', 'cycle_number' => 1,
            'modality_id' => $this->modality->getKey(), 'is_active' => true,
        ]);
        $this->oldTutoring = $this->createTutoring(subject: $this->oldSubject);
    }

    /** Estudiante del paralelo de las tutorías que sigue `$malla`. */
    private function student(Malla $malla, string $name = 'Ana'): Usuario
    {
        $student = Usuario::factory()->withRole('estudiante')->create(['nombre' => $name, 'fk_carrera' => $this->career->getKey(), 'fk_ciclo' => $this->cycle->getKey()]);
        $student->forceFill(['malla_id' => $malla->getKey()])->save();
        $student->paralelos()->attach($this->cycle->fk_paralelo, ['fecha_asignacion' => today(), 'estado' => true]);

        return $student;
    }

    private function enroll(Usuario $student, AsignaturaTutoria $tutoring): InscripcionTutoria
    {
        return InscripcionTutoria::query()->create(['fk_asig_tutoria' => $tutoring->getKey(), 'fk_id_usuario' => $student->getKey(), 'fecha_inscripcion' => today(), 'estado' => true]);
    }

    public function test_tutorings_are_filtered_by_malla_and_show_it(): void
    {
        Sanctum::actingAs($this->coordinator, ['*']);

        $this->getJson(self::API.'/tutorings?malla_id='.$this->old->getKey())->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->oldTutoring->getKey())
            ->assertJsonPath('data.0.malla_id', $this->old->getKey())
            ->assertJsonPath('data.0.malla_name', 'MALLA 2016');
        $this->getJson(self::API.'/tutorings?malla_id='.$this->current->getKey())->assertOk()
            ->assertJsonPath('data.*.id', [$this->currentTutoring->getKey()])
            ->assertJsonPath('data.0.malla_name', 'MALLA INICIAL');
        $this->getJson(self::API.'/tutorings')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_students_are_filtered_by_malla_and_show_it(): void
    {
        $ana = $this->student($this->current, 'Ana');
        $beto = $this->student($this->old, 'Beto');
        Sanctum::actingAs($this->coordinator, ['*']);

        $this->getJson(self::API.'/students?career_id='.$this->career->getKey().'&malla_id='.$this->old->getKey())->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $beto->getKey())
            ->assertJsonPath('data.0.malla_id', $this->old->getKey())
            ->assertJsonPath('data.0.malla_name', 'MALLA 2016');
        $this->getJson(self::API.'/students?career_id='.$this->career->getKey().'&malla_id='.$this->current->getKey())->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $ana->getKey())->assertJsonPath('data.0.malla_name', 'MALLA INICIAL');
        $this->getJson(self::API.'/students?career_id='.$this->career->getKey())->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_reports_are_filtered_by_malla(): void
    {
        $reports = collect([$this->currentTutoring, $this->oldTutoring])->map(fn (AsignaturaTutoria $tutoring) => Reporte::query()->create([
            'fk_asig_tutoria' => $tutoring->getKey(), 'fk_id_usuario' => $this->coordinator->getKey(), 'tipo_reporte' => 'Seguimiento',
            'fecha_generacion' => now(), 'summary' => ['format' => TutoringFollowUpReport::FORMAT],
        ]));
        Sanctum::actingAs($this->coordinator, ['*']);

        $this->getJson(self::API.'/reports?malla_id='.$this->old->getKey())->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $reports[1]->getKey());
        $this->getJson(self::API.'/reports?malla_id='.$this->current->getKey())->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $reports[0]->getKey());
        $this->getJson(self::API.'/reports')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_coordinator_moves_a_student_to_another_malla_of_the_career(): void
    {
        $student = $this->student($this->current);
        Sanctum::actingAs($this->coordinator, ['*']);

        $this->patchJson(self::API."/students/{$student->getKey()}/malla", ['malla_id' => $this->old->getKey()])->assertOk()
            ->assertJsonPath('data.id', $student->getKey())
            ->assertJsonPath('data.malla_id', $this->old->getKey())
            ->assertJsonPath('data.malla_name', 'MALLA 2016');
        $this->assertSame($this->old->getKey(), $student->refresh()->malla_id);

        $foreign = $this->currentMalla($this->otherCareer);
        $this->patchJson(self::API."/students/{$student->getKey()}/malla", ['malla_id' => $foreign->getKey()])
            ->assertUnprocessable()->assertJsonValidationErrors(['malla_id' => 'La malla debe ser de la carrera del estudiante.']);
        $this->patchJson(self::API."/students/{$student->getKey()}/malla", [])->assertUnprocessable()->assertJsonValidationErrors('malla_id');
        $this->assertSame($this->old->getKey(), $student->refresh()->malla_id);

        $outsider = Usuario::factory()->withRole('estudiante')->create(['fk_carrera' => $this->otherCareer->getKey()]);
        $this->patchJson(self::API."/students/{$outsider->getKey()}/malla", ['malla_id' => $foreign->getKey()])->assertForbidden();
        $teacher = Usuario::factory()->withRole('docente')->create(['fk_carrera' => $this->career->getKey()]);
        $this->patchJson(self::API."/students/{$teacher->getKey()}/malla", ['malla_id' => $this->old->getKey()])->assertForbidden();
        $this->assertNull($teacher->refresh()->malla_id);
    }

    public function test_a_student_with_active_tutorings_of_the_other_malla_cannot_move(): void
    {
        $student = $this->student($this->current);
        $enrollment = $this->enroll($student, $this->currentTutoring);
        Sanctum::actingAs($this->coordinator, ['*']);

        $this->patchJson(self::API."/students/{$student->getKey()}/malla", ['malla_id' => $this->old->getKey()])
            ->assertUnprocessable()->assertJsonValidationErrors(['malla_id' => 'Retira primero sus tutorías activas de la otra malla.']);
        $this->assertSame($this->current->getKey(), $student->refresh()->malla_id);

        // Una inscripción retirada o de un período pasado no lo impide.
        $enrollment->update(['estado' => false]);
        $past = PeriodoAcademico::query()->create(['nombre' => 'PAO 2025', 'fecha_inicio' => '2025-05-01', 'fecha_fin' => '2025-10-31', 'estado' => false]);
        $pastTutoring = AsignaturaTutoria::query()->create([
            'subject_id' => $this->currentTutoring->subject_id, 'fk_ciclo' => $this->cycle->getKey(), 'fk_periodo' => $past->getKey(),
            'fk_modalidad' => $this->modality->getKey(), 'fk_paralelo' => $this->cycle->fk_paralelo, 'nombre' => 'CALIDAD', 'estado' => true,
        ]);
        $this->enroll($student, $pastTutoring);
        $this->patchJson(self::API."/students/{$student->getKey()}/malla", ['malla_id' => $this->old->getKey()])->assertOk()
            ->assertJsonPath('data.malla_id', $this->old->getKey());
    }

    public function test_enrolling_a_student_in_a_tutoring_of_another_malla_is_rejected(): void
    {
        $student = $this->student($this->current);
        Sanctum::actingAs($this->coordinator, ['*']);

        $this->postJson(self::API."/tutorings/{$this->oldTutoring->getKey()}/students", ['student_id' => $student->getKey()])
            ->assertUnprocessable()->assertJsonValidationErrors(['student_id' => 'El estudiante sigue otra malla.']);
        $this->postJson(self::API."/students/{$student->getKey()}/enroll", ['tutoring_id' => $this->oldTutoring->getKey()])
            ->assertUnprocessable()->assertJsonValidationErrors(['tutoring_id' => 'El estudiante sigue otra malla.']);
        $this->assertDatabaseMissing('inscripcion_tutoria', ['fk_id_usuario' => $student->getKey()]);

        // Una inscripción retirada de otra malla tampoco se rehabilita.
        $old = InscripcionTutoria::query()->create(['fk_asig_tutoria' => $this->oldTutoring->getKey(), 'fk_id_usuario' => $student->getKey(), 'fecha_inscripcion' => today(), 'estado' => false]);
        $this->patchJson(self::API."/tutorings/{$this->oldTutoring->getKey()}/students/{$old->getKey()}/reenroll")
            ->assertUnprocessable()->assertJsonValidationErrors(['student_id' => 'El estudiante sigue otra malla.']);
        $this->assertFalse($old->refresh()->estado);

        $this->postJson(self::API."/tutorings/{$this->currentTutoring->getKey()}/students", ['student_id' => $student->getKey()])->assertCreated();
    }

    public function test_teachers_cannot_enroll_a_student_of_another_malla(): void
    {
        $teacher = Usuario::factory()->withRole('docente')->create();
        $this->oldTutoring->update(['fk_docente' => $teacher->getKey()]);
        $student = $this->student($this->current);
        $follower = $this->student($this->old, 'Beto');
        Sanctum::actingAs($teacher, ['access-api']);
        $path = '/api/v1/teacher/tutorings/'.$this->oldTutoring->getKey();

        $this->getJson($path.'/available-students')->assertOk()->assertJsonPath('data.*.id', [$follower->getKey()]);
        $this->postJson($path.'/students', ['student_id' => $student->getKey()])
            ->assertUnprocessable()->assertJsonValidationErrors(['student_id' => 'El estudiante sigue otra malla.']);
        $this->postJson($path.'/students', ['student_id' => $follower->getKey()])->assertCreated();
        $this->assertSame([$follower->getKey()], InscripcionTutoria::query()->where('fk_asig_tutoria', $this->oldTutoring->getKey())->pluck('fk_id_usuario')->all());
    }

    public function test_available_lists_leave_out_students_and_tutorings_of_another_malla(): void
    {
        $ana = $this->student($this->current, 'Ana');
        $beto = $this->student($this->old, 'Beto');
        Sanctum::actingAs($this->coordinator, ['*']);

        $this->getJson(self::API."/tutorings/{$this->oldTutoring->getKey()}/available-students")->assertOk()
            ->assertJsonPath('data.*.id', [$beto->getKey()]);
        $this->getJson(self::API."/tutorings/{$this->currentTutoring->getKey()}/available-students")->assertOk()
            ->assertJsonPath('data.*.id', [$ana->getKey()]);
        $this->getJson(self::API."/students/{$ana->getKey()}/available-tutorings")->assertOk()
            ->assertJsonPath('data.*.id', [$this->currentTutoring->getKey()]);
        $this->getJson(self::API."/students/{$beto->getKey()}/available-tutorings")->assertOk()
            ->assertJsonPath('data.*.id', [$this->oldTutoring->getKey()]);
    }

    public function test_new_students_follow_the_tutoring_malla_or_otherwise_the_current_one(): void
    {
        Sanctum::actingAs($this->coordinator, ['*']);
        $person = fn (string $email, string $id): array => ['identification' => $id, 'first_names' => 'Carlos', 'last_names' => 'Nuevo', 'email' => $email];

        $this->postJson(self::API."/tutorings/{$this->oldTutoring->getKey()}/students", $person('uno@ueb.edu.ec', '0926687856'))->assertCreated();
        $this->postJson(self::API.'/students', [...$person('dos@ueb.edu.ec', '0102030400'), 'cycle_id' => $this->cycle->getKey(), 'tutoring_id' => $this->oldTutoring->getKey()])
            ->assertCreated()->assertJsonPath('data.malla_id', $this->old->getKey())->assertJsonPath('data.malla_name', 'MALLA 2016');
        $this->postJson(self::API.'/students', [...$person('tres@ueb.edu.ec', '1710034065'), 'cycle_id' => $this->cycle->getKey()])
            ->assertCreated()->assertJsonPath('data.malla_id', $this->current->getKey());

        $teacher = Usuario::factory()->withRole('docente')->create();
        $this->oldTutoring->update(['fk_docente' => $teacher->getKey()]);
        Sanctum::actingAs($teacher, ['access-api']);
        $this->postJson('/api/v1/teacher/tutorings/'.$this->oldTutoring->getKey().'/students', $person('cuatro@ueb.edu.ec', '0401234562'))->assertCreated();

        $mallas = Usuario::query()->whereIn('correo', ['uno@ueb.edu.ec', 'dos@ueb.edu.ec', 'tres@ueb.edu.ec', 'cuatro@ueb.edu.ec'])->pluck('malla_id', 'correo')->all();
        $this->assertSame([
            'uno@ueb.edu.ec' => $this->old->getKey(), 'dos@ueb.edu.ec' => $this->old->getKey(),
            'tres@ueb.edu.ec' => $this->current->getKey(), 'cuatro@ueb.edu.ec' => $this->old->getKey(),
        ], collect(['uno@ueb.edu.ec', 'dos@ueb.edu.ec', 'tres@ueb.edu.ec', 'cuatro@ueb.edu.ec'])->mapWithKeys(fn (string $email): array => [$email => $mallas[$email]])->all());
    }

    public function test_coordinator_registers_a_student_in_a_chosen_malla_of_the_career(): void
    {
        Sanctum::actingAs($this->coordinator, ['*']);
        $payload = ['identification' => '0926687856', 'first_names' => 'Carlos', 'last_names' => 'Nuevo', 'email' => 'uno@ueb.edu.ec', 'cycle_id' => $this->cycle->getKey()];

        $this->postJson(self::API.'/students', [...$payload, 'malla_id' => $this->currentMalla($this->otherCareer)->getKey()])
            ->assertUnprocessable()->assertJsonValidationErrors(['malla_id' => 'La malla no pertenece a la carrera.']);
        $this->assertDatabaseMissing('usuario', ['correo' => 'uno@ueb.edu.ec']);

        $this->postJson(self::API.'/students', [...$payload, 'malla_id' => $this->old->getKey()])->assertCreated()
            ->assertJsonPath('data.malla_id', $this->old->getKey())->assertJsonPath('data.malla_name', 'MALLA 2016');
        $this->assertSame($this->old->getKey(), Usuario::query()->where('correo', 'uno@ueb.edu.ec')->value('malla_id'));
    }

    public function test_degree_students_are_filtered_by_malla(): void
    {
        $degreeCycle = $this->createCycle($this->career, null, 6);
        $ana = Usuario::factory()->withRole('estudiante')->create(['nombre' => 'Ana', 'fk_carrera' => $this->career->getKey(), 'fk_ciclo' => $degreeCycle->getKey()]);
        $beto = Usuario::factory()->withRole('estudiante')->create(['nombre' => 'Beto', 'fk_carrera' => $this->career->getKey(), 'fk_ciclo' => $degreeCycle->getKey()]);
        $beto->forceFill(['malla_id' => $this->old->getKey()])->save();
        Sanctum::actingAs($this->coordinator, ['*']);
        $base = self::API.'/degree-students?career_id='.$this->career->getKey();

        $this->getJson($base.'&malla_id='.$this->old->getKey())->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $beto->getKey());
        $this->getJson($base.'&malla_id='.$this->current->getKey())->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $ana->getKey());
        $this->getJson($base)->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_students_registered_elsewhere_get_the_current_malla_and_a_career_change_follows_it(): void
    {
        $admin = Usuario::factory()->withRole('administrador')->create();
        Sanctum::actingAs($admin, ['*']);

        $id = $this->postJson('/api/v1/users', [
            'identification' => '0926687856', 'first_names' => 'Ana', 'last_names' => 'Torres', 'email' => 'ana@ueb.edu.ec',
            'phone' => '0991234567', 'role' => 'estudiante', 'career_id' => $this->career->getKey(),
        ])->assertCreated()->json('data.id');
        $student = Usuario::query()->findOrFail($id);
        $this->assertSame($this->current->getKey(), $student->malla_id);

        $this->patchJson("/api/v1/users/{$id}", ['career_id' => $this->otherCareer->getKey()])->assertOk();
        $this->assertSame($this->currentMalla($this->otherCareer)->getKey(), $student->refresh()->malla_id);

        $teacherId = $this->postJson('/api/v1/users', [
            'identification' => '0102030400', 'first_names' => 'Luis', 'last_names' => 'Mora', 'email' => 'luis@ueb.edu.ec',
            'phone' => '0991234568', 'role' => 'docente', 'career_id' => $this->career->getKey(),
        ])->assertCreated()->json('data.id');
        $this->assertNull(Usuario::query()->findOrFail($teacherId)->malla_id, 'Un docente no sigue una malla.');
    }

    public function test_completing_the_profile_in_a_career_gives_its_current_malla(): void
    {
        $student = Usuario::factory()->withRole('estudiante')->create(['must_complete_profile' => true]);
        $this->assertNull($student->malla_id);

        $this->withToken($student->createToken('web', ['*'])->plainTextToken)->putJson('/api/v1/auth/profile/complete', [
            'identification' => '0926687856', 'first_names' => 'Ana', 'last_names' => 'Torres', 'phone' => '0991234567',
            'faculty_id' => Facultad::query()->firstOrFail()->getKey(), 'career_id' => $this->career->getKey(), 'cycle_id' => $this->cycle->getKey(),
            'password' => 'Nueva-Clave-2026!', 'password_confirmation' => 'Nueva-Clave-2026!',
        ])->assertOk();

        $this->assertSame($this->current->getKey(), $student->refresh()->malla_id);
    }

    public function test_automatic_advancement_stays_inside_the_malla(): void
    {
        Notification::fake();
        $secondCycle = $this->createCycle($this->career, $this->cycle->paralelo, 2);
        $past = PeriodoAcademico::query()->create(['nombre' => 'PAO 2025', 'fecha_inicio' => '2025-11-01', 'fecha_fin' => '2026-03-31', 'estado' => false]);
        $first = Subject::query()->findOrFail($this->currentTutoring->subject_id);
        $next = Subject::query()->create(['malla_id' => $this->current->getKey(), 'code' => 'CS-201', 'name' => 'CALIDAD II', 'cycle_number' => 2, 'modality_id' => $this->modality->getKey(), 'is_active' => true]);
        $first->nextSubjects()->attach($next);
        $this->currentTutoring->update(['fk_periodo' => $past->getKey()]);

        $follower = $this->student($this->current, 'Ana');
        $mover = $this->student($this->current, 'Beto');
        foreach ([$follower, $mover] as $student) {
            $this->enroll($student, $this->currentTutoring)->update(['final_result' => InscripcionTutoria::APPROVED]);
        }
        // Beto pasó a la otra malla: lo aprobado en la anterior no cuenta en ella.
        $mover->forceFill(['malla_id' => $this->old->getKey()])->save();

        Sanctum::actingAs($this->coordinator, ['*']);
        $id = $this->postJson(self::API.'/tutorings', $this->tutoringPayload($next, $secondCycle))->assertCreated()->json('data.id');

        $this->assertSame([$follower->getKey()], InscripcionTutoria::query()->where('fk_asig_tutoria', $id)->pluck('fk_id_usuario')->all());
    }
}

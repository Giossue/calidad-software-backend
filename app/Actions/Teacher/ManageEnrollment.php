<?php

namespace App\Actions\Teacher;

use App\Actions\Tutoring\AdvanceApprovedStudents;
use App\Models\AsignaturaTutoria;
use App\Models\InscripcionTutoria;
use App\Models\Role;
use App\Models\Usuario;
use App\Notifications\ProvisionalPasswordNotification;
use App\Support\ActionNotifier;
use App\Support\EnrollmentNumber;
use App\Support\Mallas;
use App\Support\PendingSubjects;
use App\Support\ProvisionalPassword;
use App\Support\StudentCycle;
use App\Support\TeacherWorkspace;
use App\Support\TutoringPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageEnrollment
{
    public const RELATIONS = ['estudiante.roles', 'notas', 'knowledgeMetric'];

    public function __construct(private TeacherWorkspace $workspace, private StudentCycle $cycles, private AdvanceApprovedStudents $advance) {}

    /** @param array<string, mixed> $data */
    public function create(Usuario $teacher, AsignaturaTutoria $tutoring, array $data): InscripcionTutoria
    {
        return $this->workspace->write($teacher, $tutoring, function (AsignaturaTutoria $locked) use ($data): InscripcionTutoria {
            TutoringPeriod::assertCurrent($locked);
            if (isset($data['student_id'])) {
                $student = Usuario::query()->whereKey($data['student_id'])->lockForUpdate()->firstOrFail();
                if (! $student->estado || ! $student->hasRole('estudiante') || ! $student->paralelos()->whereKey($locked->fk_paralelo)->wherePivot('estado', true)->exists()) {
                    throw ValidationException::withMessages(['student_id' => 'Selecciona un estudiante activo del paralelo de esta tutoría.']);
                }
                $this->cycles->assertCanTakeTutorings($student);
                Mallas::assertFollows($student, $locked);
                PendingSubjects::assertCanTake($student, $locked);
                $this->cycles->assertSingleSectionPerSubject($student, $locked);
            } else {
                $password = ProvisionalPassword::generate();
                $student = Usuario::query()->create([
                    'cedula' => $data['identification'], 'nombre' => $data['name'],
                    'correo' => $data['email'], 'telefono' => $data['phone'] ?? null,
                    'password_hash' => $password, 'estado' => true, 'email_verified_at' => now(),
                    'must_complete_profile' => true,
                ]);
                $student->roles()->attach(Role::query()->where('slug', 'estudiante')->valueOrFail('id'));
                $student->paralelos()->attach($locked->fk_paralelo, ['fecha_asignacion' => today(), 'estado' => true]);
                DB::afterCommit(fn () => $student->notify(new ProvisionalPasswordNotification($password)));
            }
            // Un estudiante sin ciclo registrado pasa a cursar el ciclo de la tutoría;
            // uno nuevo sigue la malla de la tutoría.
            if ($student->fk_ciclo === null && $locked->ciclo !== null) {
                $this->cycles->assign($student, $locked->ciclo, Mallas::ofTutoring($locked));
            }
            $enrollment = InscripcionTutoria::query()->firstOrNew(['fk_asig_tutoria' => $locked->getKey(), 'fk_id_usuario' => $student->getKey()]);
            if ($enrollment->exists && $enrollment->estado) {
                throw ValidationException::withMessages(['student_id' => 'El estudiante ya está inscrito en esta tutoría.']);
            }
            $enrollment->fill([
                'fecha_inscripcion' => $enrollment->fecha_inscripcion ?? today(), 'estado' => true,
                'numero_matricula' => $data['enrollment_number'] ?? EnrollmentNumber::FIRST,
            ])->save();

            return $enrollment->load(self::RELATIONS);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Usuario $teacher, AsignaturaTutoria $tutoring, InscripcionTutoria $enrollment, array $data): InscripcionTutoria
    {
        return $this->workspace->write($teacher, $tutoring, function () use ($tutoring, $enrollment, $data): InscripcionTutoria {
            $this->workspace->enrollment($tutoring, $enrollment);
            self::applyEdit($enrollment, $data);

            return $enrollment->refresh()->load(self::RELATIONS);
        });
    }

    /**
     * Edición de la inscripción (docente o coordinación): nombre y teléfono del estudiante y su matrícula.
     * Los datos personales solo se cambian en cuentas que únicamente son de estudiante; la matrícula
     * pertenece a la inscripción y se cambia en cualquier caso.
     *
     * @param  array{name?: string, phone?: ?string, enrollment_number?: int}  $data
     */
    public static function applyEdit(InscripcionTutoria $enrollment, array $data): void
    {
        $student = $enrollment->estudiante;
        // El nombre solo cambia cuando se envían nombres y apellidos.
        $profile = [...(array_key_exists('name', $data) ? ['nombre' => $data['name']] : []), 'telefono' => $data['phone'] ?? null];
        $changesProfile = collect($profile)->contains(fn (mixed $value, string $column): bool => $student->getAttribute($column) !== $value);
        abort_if($changesProfile && ! ($student->roleSlugs()->count() === 1 && $student->hasRole('estudiante')), 403);
        if ($changesProfile) {
            $student->update($profile);
        }
        // La matrícula solo cambia cuando se envía.
        if (array_key_exists('enrollment_number', $data)) {
            $enrollment->update(['numero_matricula' => $data['enrollment_number']]);
        }
    }

    public function deactivate(Usuario $teacher, AsignaturaTutoria $tutoring, InscripcionTutoria $enrollment): InscripcionTutoria
    {
        return $this->workspace->write($teacher, $tutoring, function () use ($tutoring, $enrollment): InscripcionTutoria {
            $this->workspace->enrollment($tutoring, $enrollment);
            TutoringPeriod::assertCurrent($tutoring);
            $enrollment->update(['estado' => false]);

            return $enrollment->refresh()->load(self::RELATIONS);
        });
    }

    /** El docente marca o quita el abandono de la tutoría de un estudiante. */
    public function recordAbandonment(Usuario $teacher, AsignaturaTutoria $tutoring, InscripcionTutoria $enrollment, bool $abandoned, ?string $reason): InscripcionTutoria
    {
        return $this->workspace->write($teacher, $tutoring, function () use ($tutoring, $enrollment, $abandoned, $reason): InscripcionTutoria {
            $this->workspace->enrollment($tutoring, $enrollment);
            TutoringPeriod::assertCurrent($tutoring);

            return $this->setAbandonment($enrollment, $abandoned, $reason);
        });
    }

    /**
     * Marca el abandono con su motivo (conserva la fecha si ya estaba marcado) o lo quita.
     * Solo se marca en una inscripción activa. Lo usan docente y coordinación.
     */
    public function setAbandonment(InscripcionTutoria $enrollment, bool $abandoned, ?string $reason): InscripcionTutoria
    {
        return DB::transaction(function () use ($enrollment, $abandoned, $reason): InscripcionTutoria {
            $locked = InscripcionTutoria::query()->whereKey($enrollment->getKey())->lockForUpdate()->firstOrFail();
            if ($abandoned && ! $locked->estado) {
                throw ValidationException::withMessages(['abandoned' => 'La inscripción está deshabilitada; reinscribe al estudiante para registrar su abandono.']);
            }
            $locked->update($abandoned
                ? ['abandoned_at' => $locked->abandoned_at ?? now(), 'abandonment_reason' => $reason]
                : ['abandoned_at' => null, 'abandonment_reason' => null]);

            return $locked->refresh()->load(self::RELATIONS);
        });
    }

    /**
     * Resultado final del ciclo (`approved`, `failed` o null para quitarlo). Un aprobado
     * avanza a la tutoría de la asignatura que continúa, si ya existe.
     */
    public function recordFinalResult(Usuario $teacher, AsignaturaTutoria $tutoring, InscripcionTutoria $enrollment, ?string $result): InscripcionTutoria
    {
        return $this->workspace->write($teacher, $tutoring, function () use ($tutoring, $enrollment, $result): InscripcionTutoria {
            $this->workspace->enrollment($tutoring, $enrollment);
            if (! $enrollment->estado) {
                throw ValidationException::withMessages(['result' => 'La inscripción está deshabilitada; reinscribe al estudiante para registrar su resultado.']);
            }
            if ($enrollment->isAbandoned()) {
                throw ValidationException::withMessages(['result' => 'El estudiante abandonó la tutoría; no se puede registrar su resultado final.']);
            }
            $previous = $enrollment->final_result;
            $enrollment->update(['final_result' => $result, 'final_result_at' => $result === null ? null : now()]);
            if ($result !== null && $result !== $previous) {
                DB::afterCommit(fn () => ActionNotifier::tutoringFinalResult($tutoring, $enrollment->estudiante, $result));
            }
            if ($result === InscripcionTutoria::APPROVED) {
                if ($tutoring->subject_id !== null) {
                    PendingSubjects::resolve($enrollment->estudiante, (int) $tutoring->subject_id);
                }
                $this->advance->afterApproval($enrollment);
            }

            return $enrollment->refresh()->load(self::RELATIONS);
        });
    }
}

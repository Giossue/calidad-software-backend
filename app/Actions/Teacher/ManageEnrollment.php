<?php

namespace App\Actions\Teacher;

use App\Models\AsignaturaTutoria;
use App\Models\InscripcionTutoria;
use App\Models\Role;
use App\Models\Usuario;
use App\Notifications\ProvisionalPasswordNotification;
use App\Support\ProvisionalPassword;
use App\Support\TeacherWorkspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageEnrollment
{
    public const RELATIONS = ['estudiante.roles', 'notas', 'knowledgeMetric'];

    public function __construct(private TeacherWorkspace $workspace) {}

    /** @param array<string, mixed> $data */
    public function create(Usuario $teacher, AsignaturaTutoria $tutoring, array $data): InscripcionTutoria
    {
        return $this->workspace->write($teacher, $tutoring, function (AsignaturaTutoria $locked) use ($data): InscripcionTutoria {
            if (isset($data['student_id'])) {
                $student = Usuario::query()->whereKey($data['student_id'])->lockForUpdate()->firstOrFail();
                if (! $student->estado || ! $student->hasRole('estudiante') || ! $student->paralelos()->whereKey($locked->fk_paralelo)->wherePivot('estado', true)->exists()) {
                    throw ValidationException::withMessages(['student_id' => 'Selecciona un estudiante activo del paralelo de esta tutoría.']);
                }
            } else {
                $password = ProvisionalPassword::generate();
                $student = Usuario::query()->create([
                    'cedula' => $data['identification'], 'nombre' => $data['name'],
                    'correo' => $data['email'], 'telefono' => $data['phone'] ?? null,
                    'password_hash' => $password, 'estado' => true, 'email_verified_at' => now(),
                ]);
                $student->roles()->attach(Role::query()->where('slug', 'estudiante')->valueOrFail('id'));
                $student->paralelos()->attach($locked->fk_paralelo, ['fecha_asignacion' => today(), 'estado' => true]);
                DB::afterCommit(fn () => $student->notify(new ProvisionalPasswordNotification($password)));
            }
            $enrollment = InscripcionTutoria::query()->firstOrNew(['fk_asig_tutoria' => $locked->getKey(), 'fk_id_usuario' => $student->getKey()]);
            if ($enrollment->exists && $enrollment->estado) {
                throw ValidationException::withMessages(['student_id' => 'El estudiante ya está inscrito en esta tutoría.']);
            }
            $enrollment->fill(['fecha_inscripcion' => $enrollment->fecha_inscripcion ?? today(), 'estado' => true])->save();

            return $enrollment->load(self::RELATIONS);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Usuario $teacher, AsignaturaTutoria $tutoring, InscripcionTutoria $enrollment, array $data): InscripcionTutoria
    {
        return $this->workspace->write($teacher, $tutoring, function () use ($tutoring, $enrollment, $data): InscripcionTutoria {
            $this->workspace->enrollment($tutoring, $enrollment);
            $student = $enrollment->estudiante;
            abort_unless($student->roleSlugs()->count() === 1 && $student->hasRole('estudiante'), 403);
            $student->update(['nombre' => $data['name'], 'telefono' => $data['phone'] ?? null]);

            return $enrollment->refresh()->load(self::RELATIONS);
        });
    }

    public function deactivate(Usuario $teacher, AsignaturaTutoria $tutoring, InscripcionTutoria $enrollment): InscripcionTutoria
    {
        return $this->workspace->write($teacher, $tutoring, function () use ($tutoring, $enrollment): InscripcionTutoria {
            $this->workspace->enrollment($tutoring, $enrollment);
            $enrollment->update(['estado' => false]);

            return $enrollment->refresh()->load(self::RELATIONS);
        });
    }
}

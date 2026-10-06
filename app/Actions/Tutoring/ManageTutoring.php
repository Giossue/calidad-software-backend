<?php

namespace App\Actions\Tutoring;

use App\Models\AsignaturaTutoria;
use App\Models\Ciclo;
use App\Models\Modalidad;
use App\Models\Paralelo;
use App\Models\PeriodoAcademico;
use App\Models\Subject;
use App\Models\Usuario;
use App\Support\TutoringCoordinatorAccess;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageTutoring
{
    public function __construct(private TutoringCoordinatorAccess $access, private ManageSchedule $schedules) {}

    /**
     * Crea la tutoría de un paralelo junto con su docente y sus horarios en una
     * sola transacción: si algo falla (por ejemplo, un choque de horario del
     * docente) no queda ninguna tutoría a medias.
     *
     * @param  array{subject_id: int, cycle_id: int, period_id: int, modality_id: int, parallel_id: int|null, teacher_id: int|null, schedules: list<array{day: string, start_time: string, end_time: string, room: string|null}>}  $data
     */
    public function create(Usuario $user, array $data): AsignaturaTutoria
    {
        return $this->save(function () use ($user, $data): AsignaturaTutoria {
            $subject = Subject::query()->whereKey($data['subject_id'])->firstOrFail();
            $cycle = Ciclo::query()->whereKey($data['cycle_id'])->lockForUpdate()->firstOrFail();
            $this->access->authorizeCareer($user, $cycle->fk_carrera);

            // Cada paralelo es una tutoría propia, con su docente y su horario.
            if ($data['parallel_id'] && $data['parallel_id'] !== $cycle->fk_paralelo) {
                $cycle = Ciclo::query()->firstOrCreate(
                    ['fk_carrera' => $cycle->fk_carrera, 'numero' => $cycle->numero, 'fk_paralelo' => $data['parallel_id']],
                    ['nombre' => $cycle->nombre, 'estado' => true],
                );
                $subject->cycles()->syncWithoutDetaching([$cycle->getKey()]);
            }

            $this->validatePlacement($subject, $cycle, $data['period_id'], $data['modality_id']);
            $this->assertUnique($subject->getKey(), $cycle->getKey(), $data['period_id']);
            $this->linkPeriod($cycle, $data['period_id']);

            $tutoring = AsignaturaTutoria::query()->create([
                'subject_id' => $subject->getKey(),
                'fk_ciclo' => $cycle->getKey(),
                'fk_periodo' => $data['period_id'],
                'fk_modalidad' => $data['modality_id'],
                'fk_paralelo' => $cycle->fk_paralelo,
                'fk_docente' => null,
                'nombre' => $subject->name,
                'estado' => true,
            ]);

            if ($data['teacher_id']) {
                $this->setTeacher($tutoring, $data['teacher_id']);
            }
            $this->schedules->sync($tutoring, $data['schedules']);

            return $tutoring->refresh();
        });
    }

    /**
     * Actualiza docente, paralelo y horarios de una tutoría en una sola
     * transacción, para que un error no deje cambios a medias.
     *
     * @param  list<array{day: string, start_time: string, end_time: string, room: string|null}>  $schedules
     */
    public function configure(AsignaturaTutoria $tutoring, int $teacherId, ?int $cycleId, array $schedules): AsignaturaTutoria
    {
        return $this->save(function () use ($tutoring, $teacherId, $cycleId, $schedules): AsignaturaTutoria {
            $tutoring = AsignaturaTutoria::query()->whereKey($tutoring->getKey())->lockForUpdate()->firstOrFail();
            $this->assertActive($tutoring);

            if ($cycleId && $cycleId !== $tutoring->fk_ciclo) {
                $tutoring = $this->assignCycle($tutoring, Ciclo::query()->findOrFail($cycleId));
            }
            $this->setTeacher($tutoring, $teacherId);
            $this->schedules->sync($tutoring, $schedules);
            // Los horarios que no cambiaron también deben estar libres para el docente.
            ManageSchedule::assertTeacherAvailable($tutoring, $teacherId, $tutoring->fk_periodo, 'teacher_id');

            return $tutoring->refresh();
        });
    }

    /** @param array{period_id?: int, modality_id?: int} $data */
    public function update(AsignaturaTutoria $tutoring, array $data): AsignaturaTutoria
    {
        return $this->save(function () use ($tutoring, $data): AsignaturaTutoria {
            $tutoring = AsignaturaTutoria::query()->whereKey($tutoring->getKey())->lockForUpdate()->firstOrFail();
            $periodId = $data['period_id'] ?? $tutoring->fk_periodo;
            $modalityId = $data['modality_id'] ?? $tutoring->fk_modalidad;
            if ($periodId !== $tutoring->fk_periodo && $tutoring->inscripciones()->exists()) {
                throw ValidationException::withMessages(['period_id' => 'Una tutoría con inscripciones conserva su período para proteger el historial académico.']);
            }
            $this->validatePeriodAndModality($periodId, $modalityId, $tutoring);
            $this->assertUnique($tutoring->subject_id, $tutoring->fk_ciclo, $periodId, $tutoring->getKey());
            if ($periodId !== $tutoring->fk_periodo && $tutoring->estado && $tutoring->fk_docente) {
                ManageSchedule::assertTeacherAvailable($tutoring, $tutoring->fk_docente, $periodId, 'period_id');
            }
            $this->linkPeriod($tutoring->ciclo, $periodId);
            $tutoring->update(['fk_periodo' => $periodId, 'fk_modalidad' => $modalityId]);

            return $tutoring->refresh();
        });
    }

    public function assignCycle(AsignaturaTutoria $tutoring, Ciclo $cycle): AsignaturaTutoria
    {
        return $this->save(function () use ($tutoring, $cycle): AsignaturaTutoria {
            $tutoring = AsignaturaTutoria::query()->whereKey($tutoring->getKey())->lockForUpdate()->firstOrFail();
            $this->assertActive($tutoring);
            if ($cycle->fk_carrera !== $tutoring->ciclo->fk_carrera) {
                throw ValidationException::withMessages(['cycle_id' => 'El ciclo debe pertenecer a la carrera de la tutoría.']);
            }
            if (($cycle->getKey() !== $tutoring->fk_ciclo || $cycle->fk_paralelo !== $tutoring->fk_paralelo) && $tutoring->inscripciones()->exists()) {
                throw ValidationException::withMessages(['cycle_id' => 'Una tutoría con inscripciones conserva su ciclo y paralelo para proteger el historial académico.']);
            }
            $this->validatePlacement($tutoring->subject, $cycle, $tutoring->fk_periodo, $tutoring->fk_modalidad);
            $this->assertUnique($tutoring->subject_id, $cycle->getKey(), $tutoring->fk_periodo, $tutoring->getKey());
            $this->linkPeriod($cycle, $tutoring->fk_periodo);
            $tutoring->update(['fk_ciclo' => $cycle->getKey(), 'fk_paralelo' => $cycle->fk_paralelo]);

            return $tutoring->refresh();
        });
    }

    public function assignTeacher(AsignaturaTutoria $tutoring, Usuario $teacher): AsignaturaTutoria
    {
        return DB::transaction(function () use ($tutoring, $teacher): AsignaturaTutoria {
            $tutoring = AsignaturaTutoria::query()->whereKey($tutoring->getKey())->lockForUpdate()->firstOrFail();
            $this->assertActive($tutoring);
            // Validar que el docente no tenga conflicto de horario con los horarios activos de esta tutoría
            ManageSchedule::assertTeacherAvailable($tutoring, $teacher->getKey(), $tutoring->fk_periodo, 'teacher_id');
            $this->setTeacher($tutoring, $teacher->getKey());

            return $tutoring->refresh();
        });
    }

    /**
     * Rehabilita una tutoría deshabilitada. Conserva docente, inscripciones e
     * historial; solo exige que su período, ciclo y carrera sigan vigentes.
     */
    public function activate(AsignaturaTutoria $tutoring): AsignaturaTutoria
    {
        return DB::transaction(function () use ($tutoring): AsignaturaTutoria {
            $tutoring = AsignaturaTutoria::query()->whereKey($tutoring->getKey())->lockForUpdate()->firstOrFail();
            if ($tutoring->estado) {
                return $tutoring;
            }
            if (! $tutoring->periodo?->estado) {
                throw ValidationException::withMessages(['period_id' => 'El período de la tutoría está inactivo; no puede habilitarse.']);
            }
            if (! $tutoring->ciclo?->estado || ! $tutoring->ciclo->carrera?->estado) {
                throw ValidationException::withMessages(['cycle_id' => 'El ciclo o la carrera de la tutoría están inactivos; no puede habilitarse.']);
            }
            // Mientras estuvo deshabilitada, el docente pudo recibir otra tutoría en la misma franja.
            if ($tutoring->fk_docente) {
                ManageSchedule::assertTeacherAvailable($tutoring, $tutoring->fk_docente, $tutoring->fk_periodo, 'teacher_id');
            }
            $tutoring->update(['estado' => true]);

            return $tutoring->refresh();
        });
    }

    private function setTeacher(AsignaturaTutoria $tutoring, int $teacherId): void
    {
        $teacher = Usuario::query()->whereKey($teacherId)->lockForUpdate()->firstOrFail();
        if (! $teacher->estado || ! $teacher->hasRole('docente')) {
            throw ValidationException::withMessages(['teacher_id' => 'Selecciona un docente habilitado.']);
        }

        $tutoring->update(['fk_docente' => $teacher->getKey()]);

        if ($tutoring->ciclo && $tutoring->ciclo->fk_carrera) {
            $teacher->teachingCareers()->syncWithoutDetaching([$tutoring->ciclo->fk_carrera => ['assigned_at' => now()]]);
        }
    }

    private function assertActive(AsignaturaTutoria $tutoring): void
    {
        if (! $tutoring->estado) {
            throw ValidationException::withMessages(['tutoring_id' => 'La tutoría está deshabilitada.']);
        }
    }

    private function validatePlacement(?Subject $subject, Ciclo $cycle, int $periodId, int $modalityId): void
    {
        if (! $cycle->estado || ! $cycle->carrera->estado) {
            throw ValidationException::withMessages(['cycle_id' => 'Selecciona un ciclo y una carrera activos.']);
        }
        if ($subject && (! $subject->is_active || $subject->career_id !== $cycle->fk_carrera)) {
            throw ValidationException::withMessages(['cycle_id' => 'La asignatura y el ciclo deben estar activos y pertenecer a la misma carrera.']);
        }
        if ($subject && ! $subject->cycles()->whereKey($cycle->getKey())->exists()) {
            throw ValidationException::withMessages(['subject_id' => 'Asigna primero la asignatura al ciclo.']);
        }
        if (! $cycle->fk_paralelo || ! Paralelo::query()->whereKey($cycle->fk_paralelo)->where('estado', true)->exists()) {
            throw ValidationException::withMessages(['cycle_id' => 'El ciclo necesita un paralelo activo.']);
        }
        $this->validatePeriodAndModality($periodId, $modalityId);
    }

    private function validatePeriodAndModality(int $periodId, int $modalityId, ?AsignaturaTutoria $existing = null): void
    {
        if ($existing?->fk_periodo !== $periodId && ! PeriodoAcademico::query()->whereKey($periodId)->where('estado', true)->exists()) {
            throw ValidationException::withMessages(['period_id' => 'Selecciona un período activo.']);
        }
        if ($existing?->fk_modalidad !== $modalityId && ! Modalidad::query()->whereKey($modalityId)->where('estado', true)->exists()) {
            throw ValidationException::withMessages(['modality_id' => 'Selecciona una modalidad activa.']);
        }
    }

    private function assertUnique(?int $subjectId, int $cycleId, int $periodId, ?int $exceptId = null): void
    {
        if ($subjectId && AsignaturaTutoria::query()->where('subject_id', $subjectId)
            ->where('fk_ciclo', $cycleId)->where('fk_periodo', $periodId)
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))->exists()) {
            throw ValidationException::withMessages(['subject_id' => 'La asignatura ya tiene una tutoría en ese ciclo y período.']);
        }
    }

    private function linkPeriod(Ciclo $cycle, int $periodId): void
    {
        DB::table('ciclo_periodo')->upsert([
            'fk_ciclo' => $cycle->getKey(), 'fk_periodo' => $periodId,
            'estado' => true, 'updated_at' => now(), 'created_at' => now(),
        ], ['fk_ciclo', 'fk_periodo'], ['estado', 'updated_at']);
    }

    /** @param callable(): AsignaturaTutoria $operation */
    private function save(callable $operation): AsignaturaTutoria
    {
        try {
            return DB::transaction(fn () => $operation(), 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['subject_id' => 'La asignatura ya tiene una tutoría en ese ciclo y período.']);
        }
    }
}

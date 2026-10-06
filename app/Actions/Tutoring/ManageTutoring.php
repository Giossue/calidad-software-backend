<?php

namespace App\Actions\Tutoring;

use App\Models\AsignaturaTutoria;
use App\Models\Ciclo;
use App\Models\Horario;
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
    public function __construct(private TutoringCoordinatorAccess $access) {}

    /** @param array{subject_id: int, cycle_id: int, period_id: int, modality_id: int, parallel_ids?: array<int>|null, parallel_id?: int|null, teacher_id?: int|null, schedules?: array<int, array{day: string, start_time: string, end_time: string, room?: string|null}>|null} $data */
    public function create(Usuario $user, array $data): AsignaturaTutoria
    {
        return $this->save(function () use ($user, $data): AsignaturaTutoria {
            $subject = Subject::query()->whereKey($data['subject_id'])->firstOrFail();
            $baseCycle = Ciclo::query()->whereKey($data['cycle_id'])->lockForUpdate()->firstOrFail();
            $this->access->authorizeCareer($user, $baseCycle->fk_carrera);

            $targetParallelIds = [];
            if (! empty($data['parallel_ids']) && is_array($data['parallel_ids'])) {
                foreach ($data['parallel_ids'] as $pid) {
                    if ($pid) {
                        $targetParallelIds[] = (int) $pid;
                    }
                }
            } elseif (! empty($data['parallel_id'])) {
                $targetParallelIds[] = (int) $data['parallel_id'];
            }

            $targetParallelIds = array_values(array_unique($targetParallelIds));

            if (! empty($targetParallelIds)) {
                $created = [];
                foreach ($targetParallelIds as $parallelId) {
                    $targetCycle = Ciclo::query()->firstOrCreate(
                        [
                            'fk_carrera' => $baseCycle->fk_carrera,
                            'numero' => $baseCycle->numero,
                            'fk_paralelo' => $parallelId,
                        ],
                        [
                            'nombre' => $baseCycle->nombre,
                            'estado' => true,
                        ]
                    );

                    if (! $subject->cycles()->whereKey($targetCycle->getKey())->exists()) {
                        $subject->cycles()->syncWithoutDetaching([$targetCycle->getKey()]);
                    }

                    $this->validatePlacement($subject, $targetCycle, $data['period_id'], $data['modality_id']);
                    $this->assertUnique($subject->getKey(), $targetCycle->getKey(), $data['period_id']);
                    $this->linkPeriod($targetCycle, $data['period_id']);

                    $tutoring = AsignaturaTutoria::query()->create([
                        'subject_id' => $subject->getKey(),
                        'fk_ciclo' => $targetCycle->getKey(),
                        'fk_periodo' => $data['period_id'],
                        'fk_modalidad' => $data['modality_id'],
                        'fk_paralelo' => $targetCycle->fk_paralelo,
                        'fk_docente' => null,
                        'nombre' => $subject->name,
                        'estado' => true,
                    ]);

                    $this->applyTeacherAndSchedules($tutoring, $data, $targetCycle);

                    $created[] = $tutoring;
                }

                return $created[0];
            }

            $this->validatePlacement($subject, $baseCycle, $data['period_id'], $data['modality_id']);
            $this->assertUnique($subject->getKey(), $baseCycle->getKey(), $data['period_id']);
            $this->linkPeriod($baseCycle, $data['period_id']);

            $tutoring = AsignaturaTutoria::query()->create([
                'subject_id' => $subject->getKey(),
                'fk_ciclo' => $baseCycle->getKey(),
                'fk_periodo' => $data['period_id'],
                'fk_modalidad' => $data['modality_id'],
                'fk_paralelo' => $baseCycle->fk_paralelo,
                'fk_docente' => null,
                'nombre' => $subject->name,
                'estado' => true,
            ]);

            $this->applyTeacherAndSchedules($tutoring, $data, $baseCycle);

            return $tutoring;
        });
    }

    /** @param array{teacher_id?: int|null, schedules?: array<int, array{day: string, start_time: string, end_time: string, room?: string|null}>|null} $data */
    private function applyTeacherAndSchedules(AsignaturaTutoria $tutoring, array $data, Ciclo $cycle): void
    {
        if (empty($data['teacher_id'])) {
            return;
        }

        $teacher = Usuario::query()->whereKey($data['teacher_id'])->lockForUpdate()->firstOrFail();
        if (! $teacher->estado || ! $teacher->hasRole('docente')) {
            throw ValidationException::withMessages(['teacher_id' => 'Selecciona un docente habilitado.']);
        }

        $schedules = $data['schedules'] ?? [];
        if (! empty($schedules)) {
            $count = count($schedules);
            for ($i = 0; $i < $count; $i++) {
                $schedA = $schedules[$i];
                $dayA = trim($schedA['day']);
                $startA = substr($schedA['start_time'], 0, 5);
                $endA = substr($schedA['end_time'], 0, 5);
                $variantsA = ManageSchedule::getDayVariants($dayA);

                if ($startA >= $endA) {
                    throw ValidationException::withMessages(['end_time' => 'La hora final debe ser posterior a la inicial.']);
                }

                for ($j = $i + 1; $j < $count; $j++) {
                    $schedB = $schedules[$j];
                    $dayB = trim($schedB['day']);
                    $startB = substr($schedB['start_time'], 0, 5);
                    $endB = substr($schedB['end_time'], 0, 5);
                    $variantsB = ManageSchedule::getDayVariants($dayB);

                    if (array_intersect($variantsA, $variantsB)) {
                        if ($startA < $endB && $endA > $startB) {
                            throw ValidationException::withMessages([
                                'schedules' => 'No puedes registrar horarios superpuestos en el mismo día.',
                            ]);
                        }
                    }
                }

                $conflict = ManageSchedule::findTeacherScheduleConflict(
                    $teacher->getKey(),
                    $tutoring->fk_periodo,
                    $dayA,
                    $startA,
                    $endA,
                    $tutoring->getKey()
                );
                if ($conflict) {
                    $materia = $conflict->asignaturaTutoria?->nombre ?? 'otra asignatura';
                    $inicio = substr($conflict->hora_inicio, 0, 5);
                    $fin = substr($conflict->hora_fin, 0, 5);
                    throw ValidationException::withMessages([
                        'teacher_id' => "El docente ya tiene asignado un horario en '{$materia}' el día {$dayA} de {$inicio} a {$fin}.",
                    ]);
                }

                $room = trim($schedA['room'] ?? '') ?: 'Por asignar';
                Horario::query()->create([
                    'fk_asig_tutoria' => $tutoring->getKey(),
                    'dia_semana' => $dayA,
                    'hora_inicio' => $startA . ':00',
                    'hora_fin' => $endA . ':00',
                    'room' => $room,
                    'estado' => true,
                ]);
            }
        }

        $tutoring->update(['fk_docente' => $teacher->getKey()]);

        if ($cycle->fk_carrera) {
            $teacher->teachingCareers()->syncWithoutDetaching([$cycle->fk_carrera => ['assigned_at' => now()]]);
        }
    }

    /** @param array{teacher_id: int, cycle_id?: int|null, schedules: array<int, array{day: string, start_time: string, end_time: string, room?: string|null}>} $data */
    public function configure(AsignaturaTutoria $tutoring, array $data): AsignaturaTutoria
    {
        return $this->save(function () use ($tutoring, $data): AsignaturaTutoria {
            $tutoring = AsignaturaTutoria::query()->whereKey($tutoring->getKey())->lockForUpdate()->firstOrFail();
            $this->assertActive($tutoring);

            if (! empty($data['cycle_id']) && (int) $data['cycle_id'] !== (int) $tutoring->fk_ciclo) {
                $cycle = Ciclo::query()->whereKey($data['cycle_id'])->firstOrFail();
                $this->assignCycle($tutoring, $cycle);
                $tutoring->refresh();
            }

            $teacher = Usuario::query()->whereKey($data['teacher_id'])->lockForUpdate()->firstOrFail();
            if (! $teacher->estado || ! $teacher->hasRole('docente')) {
                throw ValidationException::withMessages(['teacher_id' => 'Selecciona un docente habilitado.']);
            }

            $schedules = $data['schedules'] ?? [];
            $count = count($schedules);
            for ($i = 0; $i < $count; $i++) {
                $schedA = $schedules[$i];
                $dayA = trim($schedA['day']);
                $startA = substr($schedA['start_time'], 0, 5);
                $endA = substr($schedA['end_time'], 0, 5);
                $variantsA = ManageSchedule::getDayVariants($dayA);

                if ($startA >= $endA) {
                    throw ValidationException::withMessages(['end_time' => 'La hora final debe ser posterior a la inicial.']);
                }

                for ($j = $i + 1; $j < $count; $j++) {
                    $schedB = $schedules[$j];
                    $dayB = trim($schedB['day']);
                    $startB = substr($schedB['start_time'], 0, 5);
                    $endB = substr($schedB['end_time'], 0, 5);
                    $variantsB = ManageSchedule::getDayVariants($dayB);

                    if (array_intersect($variantsA, $variantsB)) {
                        if ($startA < $endB && $endA > $startB) {
                            throw ValidationException::withMessages([
                                'schedules' => 'No puedes registrar horarios superpuestos en el mismo día.',
                            ]);
                        }
                    }
                }

                $conflict = ManageSchedule::findTeacherScheduleConflict(
                    $teacher->getKey(),
                    $tutoring->fk_periodo,
                    $dayA,
                    $startA,
                    $endA,
                    $tutoring->getKey()
                );
                if ($conflict) {
                    $materia = $conflict->asignaturaTutoria?->nombre ?? 'otra asignatura';
                    $inicio = substr($conflict->hora_inicio, 0, 5);
                    $fin = substr($conflict->hora_fin, 0, 5);
                    throw ValidationException::withMessages([
                        'teacher_id' => "El docente ya tiene asignado un horario en '{$materia}' el día {$dayA} de {$inicio} a {$fin}.",
                    ]);
                }
            }

            $existingActive = $tutoring->horarios()->where('estado', true)->get();
            $newDays = array_map(fn ($s) => trim($s['day']), $schedules);

            foreach ($existingActive as $existing) {
                $existingVariants = ManageSchedule::getDayVariants($existing->dia_semana);
                $matched = false;
                foreach ($newDays as $nd) {
                    if (in_array($nd, $existingVariants, true)) {
                        $matched = true;
                        break;
                    }
                }
                if (! $matched) {
                    $existing->update(['estado' => false]);
                }
            }

            foreach ($schedules as $sched) {
                $day = trim($sched['day']);
                $startTime = substr($sched['start_time'], 0, 5);
                $endTime = substr($sched['end_time'], 0, 5);
                $dayVariants = ManageSchedule::getDayVariants($day);

                $match = $existingActive->first(function ($item) use ($dayVariants) {
                    return in_array($item->dia_semana, $dayVariants, true);
                });

                if ($match) {
                    $room = trim($sched['room'] ?? '') ?: ($match->room ?: 'Por asignar');
                    $match->update([
                        'dia_semana' => $day,
                        'hora_inicio' => $startTime . ':00',
                        'hora_fin' => $endTime . ':00',
                        'room' => $room,
                        'estado' => true,
                    ]);
                } else {
                    $room = trim($sched['room'] ?? '') ?: 'Por asignar';
                    Horario::query()->create([
                        'fk_asig_tutoria' => $tutoring->getKey(),
                        'dia_semana' => $day,
                        'hora_inicio' => $startTime . ':00',
                        'hora_fin' => $endTime . ':00',
                        'room' => $room,
                        'estado' => true,
                    ]);
                }
            }

            $tutoring->update(['fk_docente' => $teacher->getKey()]);

            if ($tutoring->ciclo && $tutoring->ciclo->fk_carrera) {
                $teacher->teachingCareers()->syncWithoutDetaching([$tutoring->ciclo->fk_carrera => ['assigned_at' => now()]]);
            }

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
            $teacher = Usuario::query()->whereKey($teacher->getKey())->lockForUpdate()->firstOrFail();
            $this->assertActive($tutoring);
            if (! $teacher->estado || ! $teacher->hasRole('docente')) {
                throw ValidationException::withMessages(['teacher_id' => 'Selecciona un docente habilitado.']);
            }

            // Validar que el docente no tenga conflicto de horario con los horarios activos de esta tutoría
            $activeSchedules = $tutoring->horarios()->where('estado', true)->get();
            foreach ($activeSchedules as $sched) {
                $conflict = ManageSchedule::findTeacherScheduleConflict(
                    $teacher->getKey(),
                    $tutoring->fk_periodo,
                    $sched->dia_semana,
                    $sched->hora_inicio,
                    $sched->hora_fin,
                    $tutoring->getKey()
                );
                if ($conflict) {
                    $materia = $conflict->asignaturaTutoria?->nombre ?? 'otra asignatura';
                    $inicio = substr($conflict->hora_inicio, 0, 5);
                    $fin = substr($conflict->hora_fin, 0, 5);
                    throw ValidationException::withMessages([
                        'teacher_id' => "El docente ya tiene asignado un horario en '{$materia}' el día {$sched->dia_semana} de {$inicio} a {$fin}.",
                    ]);
                }
            }

            $tutoring->update(['fk_docente' => $teacher->getKey()]);

            if ($tutoring->ciclo && $tutoring->ciclo->fk_carrera) {
                $teacher->teachingCareers()->syncWithoutDetaching([$tutoring->ciclo->fk_carrera => ['assigned_at' => now()]]);
            }

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
            $tutoring->update(['estado' => true]);

            return $tutoring->refresh();
        });
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

<?php

namespace App\Actions\Tutoring;

use App\Models\AsignaturaTutoria;
use App\Models\Ciclo;
use App\Models\Paralelo;
use App\Models\Subject;
use App\Models\Usuario;
use App\Support\TutoringCoordinatorAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageSubject
{
    public function __construct(private TutoringCoordinatorAccess $access) {}

    /** @param array{career_id: int, code?: string|null, name: string, cycle_id?: int|null, parallel_id?: int|null, parallel_ids?: array<int>|null, new_parallel_name?: string|null, modality_id?: int|null, period_id?: int|null} $data */
    public function create(Usuario $user, array $data): Subject
    {
        $this->access->authorizeCareer($user, $data['career_id']);

        return DB::transaction(function () use ($data): Subject {
            $subject = Subject::query()->create([
                'career_id' => $data['career_id'],
                'code' => ! empty($data['code']) ? trim($data['code']) : null,
                'name' => trim($data['name']),
                'modality_id' => ! empty($data['modality_id']) ? (int) $data['modality_id'] : null,
                'is_active' => true,
            ]);

            if (! empty($data['cycle_id'])) {
                $baseCycle = Ciclo::query()->findOrFail($data['cycle_id']);
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

                if (! empty($data['new_parallel_name'])) {
                    $parallel = Paralelo::query()->firstOrCreate(
                        ['nombre' => trim($data['new_parallel_name'])],
                        ['estado' => true]
                    );
                    $targetParallelIds[] = $parallel->getKey();
                }

                $targetParallelIds = array_values(array_unique($targetParallelIds));

                if (! empty($targetParallelIds)) {
                    foreach ($targetParallelIds as $parallelId) {
                        $targetCycle = Ciclo::query()->firstOrCreate(
                            [
                                'fk_carrera' => $data['career_id'],
                                'numero' => $baseCycle->numero,
                                'fk_paralelo' => $parallelId,
                            ],
                            [
                                'nombre' => $baseCycle->nombre,
                                'estado' => true,
                            ]
                        );
                        $this->assignCycle($subject, $targetCycle);
                        if (! empty($data['period_id'])) {
                            $this->linkPeriod($targetCycle, (int) $data['period_id']);
                        }
                    }
                } else {
                    $this->assignCycle($subject, $baseCycle);
                    if (! empty($data['period_id'])) {
                        $this->linkPeriod($baseCycle, (int) $data['period_id']);
                    }
                }
            }

            return $subject;
        });
    }

    /** @param array{code?: string|null, name?: string, modality_id?: int|null} $data */
    public function update(Subject $subject, array $data): Subject
    {
        $subject->fill(array_filter([
            'code' => array_key_exists('code', $data) ? ($data['code'] !== null && trim($data['code']) !== '' ? trim($data['code']) : null) : null,
            'name' => isset($data['name']) ? trim($data['name']) : null,
            'modality_id' => array_key_exists('modality_id', $data) ? ($data['modality_id'] ? (int) $data['modality_id'] : null) : null,
        ], fn ($value) => $value !== null))->save();

        if (array_key_exists('code', $data) && ($data['code'] === null || trim($data['code']) === '')) {
            $subject->forceFill(['code' => null])->save();
        }

        if (array_key_exists('modality_id', $data) && empty($data['modality_id'])) {
            $subject->forceFill(['modality_id' => null])->save();
        }

        return $subject->refresh();
    }

    public function linkPeriod(Ciclo $cycle, int $periodId): void
    {
        DB::table('ciclo_periodo')->upsert([
            'fk_ciclo' => $cycle->getKey(),
            'fk_periodo' => $periodId,
            'estado' => true,
            'updated_at' => now(),
            'created_at' => now(),
        ], ['fk_ciclo', 'fk_periodo'], ['estado', 'updated_at']);
    }

    public function assignCycle(Subject $subject, Ciclo $cycle): Subject
    {
        if (! $subject->is_active || ! $cycle->estado || $subject->career_id !== $cycle->fk_carrera) {
            throw ValidationException::withMessages(['cycle_id' => 'El ciclo debe estar activo y pertenecer a la carrera de la asignatura.']);
        }

        $subject->cycles()->syncWithoutDetaching([$cycle->getKey()]);

        return $subject->load('cycles', 'career');
    }

    public function unassignCycle(Subject $subject, Ciclo $cycle): Subject
    {
        $hasActiveTutoring = AsignaturaTutoria::query()
            ->where('subject_id', $subject->getKey())
            ->where('fk_ciclo', $cycle->getKey())
            ->where('estado', true)
            ->exists();

        if ($hasActiveTutoring) {
            throw ValidationException::withMessages(['cycle_id' => 'No puedes desasignar este ciclo: hay una tutoría activa que lo usa.']);
        }

        $subject->cycles()->detach($cycle->getKey());

        return $subject->load('cycles', 'career');
    }

    /** @param array{parallel_id?: int|null, new_parallel_name?: string|null, cycle_id?: int|null} $data */
    public function assignParallel(Subject $subject, array $data): Subject
    {
        if (! $subject->is_active) {
            throw ValidationException::withMessages(['parallel_id' => 'La asignatura debe estar activa.']);
        }

        $baseCycle = null;
        if (! empty($data['cycle_id'])) {
            $baseCycle = Ciclo::query()->where('fk_carrera', $subject->career_id)->find($data['cycle_id']);
        }
        if (! $baseCycle) {
            $baseCycle = $subject->cycles()->first();
        }

        if (! $baseCycle) {
            throw ValidationException::withMessages(['cycle_id' => 'La asignatura debe tener un ciclo asignado para gestionar sus paralelos.']);
        }

        $parallelId = $data['parallel_id'] ?? null;
        if (! empty($data['new_parallel_name'])) {
            $parallel = Paralelo::query()->firstOrCreate(
                ['nombre' => trim($data['new_parallel_name'])],
                ['estado' => true]
            );
            $parallelId = $parallel->getKey();
        }

        if (! $parallelId) {
            throw ValidationException::withMessages(['parallel_id' => 'Debe seleccionar o indicar un paralelo.']);
        }

        $targetCycle = Ciclo::query()->firstOrCreate(
            [
                'fk_carrera' => $subject->career_id,
                'numero' => $baseCycle->numero,
                'fk_paralelo' => $parallelId,
            ],
            [
                'nombre' => $baseCycle->nombre,
                'estado' => true,
            ]
        );

        return $this->assignCycle($subject, $targetCycle);
    }

    public function unassignParallel(Subject $subject, Paralelo $parallel): Subject
    {
        $cycle = $subject->cycles()->where('ciclo.fk_paralelo', $parallel->getKey())->first();

        if (! $cycle) {
            $cycle = $subject->cycles->first(fn ($c) => (int) $c->fk_paralelo === (int) $parallel->getKey());
        }

        if (! $cycle) {
            throw ValidationException::withMessages(['parallel_id' => 'El paralelo no está asignado a esta asignatura.']);
        }

        $hasActiveTutoring = AsignaturaTutoria::query()
            ->where('subject_id', $subject->getKey())
            ->where('fk_ciclo', $cycle->getKey())
            ->where('estado', true)
            ->exists();

        if ($hasActiveTutoring) {
            throw ValidationException::withMessages(['parallel_id' => 'No puedes desasignar este paralelo: hay una tutoría activa que lo usa.']);
        }

        $subject->cycles()->detach($cycle->getKey());

        return $subject->load('cycles', 'career');
    }
}

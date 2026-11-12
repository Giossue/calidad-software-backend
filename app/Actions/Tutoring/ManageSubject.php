<?php

namespace App\Actions\Tutoring;

use App\Http\Requests\Api\V1\Tutoring\SubjectRequest;
use App\Models\AsignaturaTutoria;
use App\Models\Carrera;
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

    /**
     * La asignatura pertenece a la malla: código, nombre, número de ciclo y previas.
     * No lleva paralelos; el paralelo se elige al crear cada tutoría del período.
     * `cycle_id` se acepta por compatibilidad y aporta solo su número de ciclo.
     *
     * Toda asignatura tiene modalidad: sin una elegida, toma la de su carrera.
     *
     * @param  array{career_id: int, code?: string|null, name: string, cycle_number?: int|null, cycle_id?: int|null, modality_id?: int|null, next_subject_ids?: array<int, int>|null}  $data
     */
    public function create(Usuario $user, array $data): Subject
    {
        $this->access->authorizeCareer($user, $data['career_id']);

        return DB::transaction(function () use ($data): Subject {
            $cycleNumber = ! empty($data['cycle_number']) ? (int) $data['cycle_number']
                : (! empty($data['cycle_id']) ? (int) Ciclo::query()->whereKey($data['cycle_id'])->value('numero') : null);

            $subject = Subject::query()->create([
                'career_id' => $data['career_id'],
                'code' => ! empty($data['code']) ? trim($data['code']) : null,
                'name' => SubjectRequest::normalizeName($data['name']),
                'cycle_number' => $cycleNumber ?: null,
                'modality_id' => ! empty($data['modality_id']) ? (int) $data['modality_id'] : self::careerModality($data['career_id']),
                'is_active' => true,
            ]);

            if (array_key_exists('next_subject_ids', $data)) {
                $this->syncNextSubjects($subject, $data['next_subject_ids'] ?? []);
            }

            return $subject;
        });
    }

    /**
     * Modalidad de la carrera, que heredan sus asignaturas. Una carrera sin
     * modalidad no puede recibir asignaturas.
     */
    public static function careerModality(int $careerId): int
    {
        $modalityId = Carrera::query()->whereKey($careerId)->value('fk_modalidad');
        if ($modalityId === null) {
            throw ValidationException::withMessages(['career_id' => 'La carrera no tiene modalidad; pide al administrador que la registre.']);
        }

        return (int) $modalityId;
    }

    /**
     * Asignaturas que continúan a `$subject` en la malla. No se permiten ciclos
     * (A → B → A): la siguiente no puede llevar de vuelta a `$subject`.
     *
     * @param  array<int, int|string>  $nextIds
     */
    public function syncNextSubjects(Subject $subject, array $nextIds): void
    {
        $nextIds = array_values(array_unique(array_map('intval', $nextIds)));
        foreach ($nextIds as $nextId) {
            if ($this->leadsTo($nextId, $subject->getKey())) {
                $name = Subject::query()->whereKey($nextId)->value('name');
                throw ValidationException::withMessages(['next_subject_ids' => "«{$name}» ya lleva a esta asignatura en la secuencia; no puede continuarla."]);
            }
        }
        $subject->nextSubjects()->sync($nextIds);
    }

    /** `$from` lleva a `$target` siguiendo la secuencia. */
    private function leadsTo(int $from, int $target): bool
    {
        $pending = [$from];
        $seen = [];
        while ($pending !== []) {
            $current = array_pop($pending);
            if ($current === $target) {
                return true;
            }
            if (isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;
            array_push($pending, ...DB::table('subject_sequences')->where('subject_id', $current)->pluck('next_subject_id')->all());
        }

        return false;
    }

    /** @param array{code?: string|null, name?: string, cycle_number?: int|null, modality_id?: int|null, next_subject_ids?: array<int, int>|null} $data */
    public function update(Subject $subject, array $data): Subject
    {
        $originalName = $subject->name;

        if (array_key_exists('cycle_number', $data)) {
            $cycleNumber = $data['cycle_number'] ? (int) $data['cycle_number'] : null;
            // Las tutorías del período vigente ya eligieron un paralelo de ese ciclo.
            $placed = AsignaturaTutoria::query()->where('subject_id', $subject->getKey())->where('estado', true)
                ->whereHas('periodo', fn ($query) => $query->where('estado', true))
                ->whereHas('ciclo', fn ($query) => $query->where('numero', '!=', $cycleNumber ?? 0))
                ->exists();
            if ($placed && $cycleNumber !== $subject->cycleNumber()) {
                throw ValidationException::withMessages(['cycle_number' => 'La asignatura tiene tutorías activas en el período vigente; deshabilítalas antes de cambiar su ciclo.']);
            }
            $subject->forceFill(['cycle_number' => $cycleNumber])->save();
        }

        $subject->fill(array_filter([
            'code' => array_key_exists('code', $data) ? ($data['code'] !== null && trim($data['code']) !== '' ? trim($data['code']) : null) : null,
            'name' => isset($data['name']) ? SubjectRequest::normalizeName($data['name']) : null,
            'modality_id' => ! empty($data['modality_id']) ? (int) $data['modality_id'] : null,
        ], fn ($value) => $value !== null))->save();

        if (array_key_exists('code', $data) && ($data['code'] === null || trim($data['code']) === '')) {
            $subject->forceFill(['code' => null])->save();
        }

        if (array_key_exists('next_subject_ids', $data)) {
            $this->syncNextSubjects($subject, $data['next_subject_ids'] ?? []);
        }

        // Las tutorías guardan una copia del nombre de la asignatura.
        if ($subject->name !== $originalName) {
            AsignaturaTutoria::query()->where('subject_id', $subject->getKey())->update(['nombre' => $subject->name]);
        }

        return $subject->refresh();
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

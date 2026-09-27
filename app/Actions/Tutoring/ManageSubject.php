<?php

namespace App\Actions\Tutoring;

use App\Models\Ciclo;
use App\Models\Subject;
use App\Models\Usuario;
use App\Support\TutoringCoordinatorAccess;
use Illuminate\Validation\ValidationException;

class ManageSubject
{
    public function __construct(private TutoringCoordinatorAccess $access) {}

    /** @param array{career_id: int, code: string, name: string} $data */
    public function create(Usuario $user, array $data): Subject
    {
        $this->access->authorizeCareer($user, $data['career_id']);

        return Subject::query()->create([
            'career_id' => $data['career_id'],
            'code' => trim($data['code']),
            'name' => trim($data['name']),
            'is_active' => true,
        ]);
    }

    /** @param array{code?: string, name?: string} $data */
    public function update(Subject $subject, array $data): Subject
    {
        $subject->fill(array_filter([
            'code' => isset($data['code']) ? trim($data['code']) : null,
            'name' => isset($data['name']) ? trim($data['name']) : null,
        ], fn ($value) => $value !== null))->save();

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
}

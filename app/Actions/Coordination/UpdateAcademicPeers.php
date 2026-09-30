<?php

namespace App\Actions\Coordination;

use App\Models\AsignacionDocente;
use App\Models\TemaTitulacion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateAcademicPeers
{
    public function __construct(
        private readonly ValidateDegreeTopicTeachers $validateTeachers,
        private readonly SyncAcademicPeers $syncPeers,
    ) {}

    /**
     * @param  array<int, int>  $peerIds
     * @return Collection<int, AsignacionDocente>
     */
    public function handle(TemaTitulacion $topic, array $peerIds): Collection
    {
        return DB::transaction(function () use ($topic, $peerIds): Collection {
            $topic = TemaTitulacion::query()->whereKey($topic->getKey())->lockForUpdate()->firstOrFail();

            if ($topic->estado !== 'aprobado') {
                throw ValidationException::withMessages([
                    'topic' => ['Solo se pueden reasignar pares académicos de temas aprobados.'],
                ]);
            }

            $tutorId = $topic->activeAssignments()->where('rol', 'tutor')->value('fk_id_usuario');
            if ($tutorId === null) {
                throw ValidationException::withMessages([
                    'tutor_id' => ['El tema debe tener un docente tutor activo antes de reasignar sus pares.'],
                ]);
            }

            $this->validateTeachers->handle((int) $tutorId, $peerIds);
            $this->syncPeers->handle($topic, $peerIds);

            return $topic->activeAssignments()->with('docente')->where('rol', 'par_academico')->get();
        });
    }
}

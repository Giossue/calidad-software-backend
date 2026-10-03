<?php

namespace App\Actions\Coordination;

use App\Models\AsignacionDocente;
use App\Models\TemaTitulacion;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveDegreeTopic
{
    public function __construct(
        private readonly ValidateDegreeTopicTeachers $validateTeachers,
        private readonly SyncAcademicPeers $syncPeers,
    ) {}

    /** @param array<int, int> $peerIds */
    public function handle(TemaTitulacion $topic, Usuario $coordinator, int $tutorId, array $peerIds): TemaTitulacion
    {
        return DB::transaction(function () use ($topic, $coordinator, $tutorId, $peerIds): TemaTitulacion {
            $topic = TemaTitulacion::query()->whereKey($topic->getKey())->lockForUpdate()->firstOrFail();

            if ($topic->estado !== 'pendiente') {
                throw ValidationException::withMessages([
                    'topic' => ['Solo se pueden aprobar propuestas pendientes de revisión.'],
                ]);
            }

            $this->validateTeachers->handle($tutorId, $peerIds);

            $topic->update([
                'estado' => 'aprobado',
                'fecha_revision' => now()->toDateString(),
                'fk_coord_revisor' => $coordinator->getKey(),
            ]);

            $topic->asignaciones()->where('rol', 'tutor')->where('estado', true)
                ->update(['estado' => false]);

            AsignacionDocente::query()->updateOrCreate(
                ['fk_tema_tit' => $topic->getKey(), 'fk_id_usuario' => $tutorId, 'rol' => 'tutor'],
                ['fecha_asignacion' => now()->toDateString(), 'estado' => true]
            );

            $this->syncPeers->handle($topic, $peerIds);

            \App\Models\FichaSeguimiento::query()->firstOrCreate(
                ['fk_tema_tit' => $topic->getKey()],
                [
                    'fecha_apertura' => now()->toDateString(),
                    'porcentaje_avance' => 0.00,
                    'estado' => 'en_progreso',
                ]
            );

            return $topic->load(TemaTitulacion::REVIEW_RELATIONS);
        });
    }
}

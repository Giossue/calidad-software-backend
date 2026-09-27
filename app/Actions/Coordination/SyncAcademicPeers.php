<?php

namespace App\Actions\Coordination;

use App\Models\AsignacionDocente;
use App\Models\TemaTitulacion;

class SyncAcademicPeers
{
    /** @param array<int, int> $peerIds */
    public function handle(TemaTitulacion $topic, array $peerIds): void
    {
        // The caller holds the topic lock. Preserve removed assignments as history.
        $topic->asignaciones()->where('rol', 'par_academico')
            ->where('estado', true)->whereNotIn('fk_id_usuario', $peerIds)
            ->update(['estado' => false]);

        foreach ($peerIds as $peerId) {
            $assignment = AsignacionDocente::query()->firstOrNew([
                'fk_tema_tit' => $topic->getKey(),
                'fk_id_usuario' => $peerId,
                'rol' => 'par_academico',
            ]);

            if (! $assignment->exists || ! $assignment->estado) {
                $assignment->fill(['fecha_asignacion' => now()->toDateString(), 'estado' => true])->save();
            }
        }
    }
}

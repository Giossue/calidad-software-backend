<?php

namespace App\Actions\Coordination;

use App\Models\Usuario;
use Illuminate\Validation\ValidationException;

class ValidateDegreeTopicTeachers
{
    /**
     * Recheck assignments under row locks in the caller's transaction.
     *
     * @param  array<int, int>  $peerIds
     */
    public function handle(?int $tutorId, array $peerIds): void
    {
        if ($peerIds === [] || count(array_unique($peerIds)) !== count($peerIds)) {
            throw ValidationException::withMessages([
                'peer_ids' => ['Seleccione al menos un par académico sin repetir docentes.'],
            ]);
        }

        $ids = array_values(array_unique(array_filter([$tutorId, ...$peerIds])));
        $teachers = Usuario::query()->with('roles')->whereKey($ids)
            ->orderBy('id_usuario')->lockForUpdate()->get()->keyBy('id_usuario');

        if ($tutorId !== null) {
            $tutor = $teachers->get($tutorId);
            if (! $tutor || ! $tutor->estado || ! $tutor->hasRole('docente')) {
                throw ValidationException::withMessages([
                    'tutor_id' => ['El tutor seleccionado debe ser un docente activo.'],
                ]);
            }
        }

        foreach ($peerIds as $index => $peerId) {
            $peer = $teachers->get($peerId);
            if (! $peer || ! $peer->estado || ! $peer->hasRole('docente')) {
                throw ValidationException::withMessages([
                    "peer_ids.{$index}" => ['El par académico seleccionado debe ser un docente activo.'],
                ]);
            }

            if ($peerId === $tutorId) {
                throw ValidationException::withMessages([
                    "peer_ids.{$index}" => ['Un docente no puede ser asignado simultáneamente como tutor y como par académico.'],
                ]);
            }
        }
    }
}

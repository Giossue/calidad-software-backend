<?php

namespace App\Actions\Coordination;

use App\Models\AsignacionDocente;
use App\Models\TemaTitulacion;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveDegreeTopic
{
    /**
     * @param  array<int, int>  $peerIds
     */
    public function handle(TemaTitulacion $topic, Usuario $coordinator, int $tutorId, array $peerIds): TemaTitulacion
    {
        if ($topic->estado === 'aprobado') {
            throw ValidationException::withMessages([
                'topic' => ['Esta propuesta de tema ya fue aprobada previamente.'],
            ]);
        }

        return DB::transaction(function () use ($topic, $coordinator, $tutorId, $peerIds): TemaTitulacion {
            $topic->update([
                'estado' => 'aprobado',
                'fecha_revision' => now()->toDateString(),
                'fk_coord_revisor' => $coordinator->getKey(),
            ]);

            // Asignación de Docente Tutor
            AsignacionDocente::query()->updateOrCreate(
                [
                    'fk_tema_tit' => $topic->getKey(),
                    'rol' => 'tutor',
                ],
                [
                    'fk_id_usuario' => $tutorId,
                    'fecha_asignacion' => now()->toDateString(),
                    'estado' => true,
                ]
            );

            // Asignación de Pares Académicos
            AsignacionDocente::query()
                ->where('fk_tema_tit', $topic->getKey())
                ->where('rol', 'par_academico')
                ->delete();

            foreach ($peerIds as $peerId) {
                AsignacionDocente::query()->create([
                    'fk_tema_tit' => $topic->getKey(),
                    'fk_id_usuario' => $peerId,
                    'rol' => 'par_academico',
                    'fecha_asignacion' => now()->toDateString(),
                    'estado' => true,
                ]);
            }

            /** @var TemaTitulacion */
            return $topic->fresh(['estudiante.paralelos', 'periodo', 'coordinadorRevisor', 'asignaciones.docente']);
        });
    }
}

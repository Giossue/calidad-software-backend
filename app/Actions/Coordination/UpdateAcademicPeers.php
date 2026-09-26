<?php

namespace App\Actions\Coordination;

use App\Models\AsignacionDocente;
use App\Models\TemaTitulacion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class UpdateAcademicPeers
{
    /**
     * @param  array<int, int>  $peerIds
     * @return Collection<int, AsignacionDocente>
     */
    public function handle(TemaTitulacion $topic, array $peerIds): Collection
    {
        return DB::transaction(function () use ($topic, $peerIds): Collection {
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

            return $topic->asignaciones()
                ->with('docente')
                ->where('rol', 'par_academico')
                ->where('estado', true)
                ->get();
        });
    }
}

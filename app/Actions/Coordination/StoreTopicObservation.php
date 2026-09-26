<?php

namespace App\Actions\Coordination;

use App\Models\ObservacionTitulacion;
use App\Models\TemaTitulacion;
use App\Models\Usuario;

class StoreTopicObservation
{
    public function handle(TemaTitulacion $topic, Usuario $coordinator, string $observation): ObservacionTitulacion
    {
        /** @var ObservacionTitulacion */
        return ObservacionTitulacion::query()->create([
            'fk_tema_tit' => $topic->getKey(),
            'fk_coord_tit' => $coordinator->getKey(),
            'descripcion' => trim($observation),
            'fecha_registro' => now()->toDateString(),
        ])->load('coordinador');
    }
}

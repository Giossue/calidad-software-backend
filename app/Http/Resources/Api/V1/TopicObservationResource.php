<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ObservacionTitulacion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ObservacionTitulacion */
class TopicObservationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $coordinator = $this->coordinador;

        return [
            'id' => $this->getKey(),
            'topic_id' => $this->fk_tema_tit,
            'observation' => $this->descripcion,
            'registered_at' => $this->fecha_registro?->toDateString(),
            'coordinator' => $coordinator ? [
                'id' => $coordinator->getKey(),
                'name' => $coordinator->nombre,
                'email' => $coordinator->correo,
            ] : null,
        ];
    }
}

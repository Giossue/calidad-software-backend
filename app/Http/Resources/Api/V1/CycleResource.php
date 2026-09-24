<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Ciclo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Ciclo */
class CycleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'career_id' => $this->fk_carrera,
            'career_name' => $this->whenLoaded('carrera', fn (): string => $this->carrera->nombre),
            'name' => $this->nombre,
            'number' => $this->numero,
            'paralelo_id' => $this->fk_paralelo,
            'paralelo_name' => $this->whenLoaded('paralelo', fn (): ?string => $this->paralelo?->nombre),
            'status' => $this->estado,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

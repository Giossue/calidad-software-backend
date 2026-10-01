<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Carrera;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Carrera */
class CareerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'faculty_id' => $this->fk_facultad,
            'faculty_name' => $this->whenLoaded('facultad', fn (): string => $this->facultad->nombre),
            'modality_id' => $this->fk_modalidad,
            'modality_name' => $this->whenLoaded('modalidad', fn (): ?string => $this->modalidad?->nombre),
            'name' => $this->nombre,
            'status' => $this->estado,
            'cycles_count' => (int) ($this->ciclos_count ?? 0),
            'active_cycles_count' => (int) ($this->active_cycles_count ?? 0),
            // Cantidad de ciclos (niveles) de la carrera: el número de ciclo más alto.
            'cycle_levels' => (int) ($this->max_cycle_number ?? 0),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

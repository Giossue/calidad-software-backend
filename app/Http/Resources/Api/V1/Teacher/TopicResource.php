<?php

namespace App\Http\Resources\Api\V1\Teacher;

use App\Models\Tema;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Tema */
class TopicResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->getKey(), 'tutoring_id' => $this->fk_asig_tutoria, 'name' => $this->nombre,
            'description' => $this->descripcion, 'is_active' => $this->estado, 'is_covered' => $this->visto,
            'activities' => ActivityResource::collection($this->whenLoaded('actividades'))];
    }
}

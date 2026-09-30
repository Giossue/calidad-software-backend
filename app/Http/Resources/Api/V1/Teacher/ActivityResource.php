<?php

namespace App\Http\Resources\Api\V1\Teacher;

use App\Models\Actividad;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Actividad */
class ActivityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->getKey(), 'topic_id' => $this->fk_tema, 'name' => $this->nombre, 'duration' => $this->duracion,
            'is_active' => $this->estado, 'methodologies' => MethodologyResource::collection($this->whenLoaded('metodologias'))];
    }
}

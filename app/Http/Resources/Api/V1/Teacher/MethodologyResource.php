<?php

namespace App\Http\Resources\Api\V1\Teacher;

use App\Models\Metodologia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Metodologia */
class MethodologyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->getKey(), 'activity_id' => $this->fk_actividad, 'description' => $this->descripcion, 'is_active' => $this->estado];
    }
}

<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Facultad;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Facultad */
class FacultyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'name' => $this->nombre,
            'status' => $this->estado,
            'is_active' => $this->estado,
            'careers_count' => (int) ($this->carreras_count ?? 0),
            'active_careers_count' => (int) ($this->active_careers_count ?? 0),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

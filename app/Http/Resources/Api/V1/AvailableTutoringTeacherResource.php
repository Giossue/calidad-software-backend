<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Usuario */
class AvailableTutoringTeacherResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'name' => $this->nombre,
            'email' => $this->correo,
            'is_active' => $this->estado,
            'busy_schedules' => $this->busy_schedules ?? [],
        ];
    }
}

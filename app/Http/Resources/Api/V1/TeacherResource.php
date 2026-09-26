<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Usuario */
class TeacherResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'identification' => $this->cedula,
            'name' => $this->nombre,
            'email' => $this->correo,
            'phone' => $this->telefono,
            'is_active' => $this->estado,
            'active_tutorships_count' => (int) ($this->tutor_assignments_count ?? 0),
            'active_peer_reviews_count' => (int) ($this->peer_assignments_count ?? 0),
        ];
    }
}

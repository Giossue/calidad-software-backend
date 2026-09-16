<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Usuario */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'identification' => $this->cedula,
            'name' => $this->nombre,
            'email' => $this->correo,
            'role' => $this->rol,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'has_two_factor' => $this->two_factor_confirmed_at !== null,
        ];
    }
}

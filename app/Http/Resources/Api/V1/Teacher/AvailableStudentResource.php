<?php

namespace App\Http\Resources\Api\V1\Teacher;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Usuario */
class AvailableStudentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->getKey(), 'identification' => $this->cedula, 'name' => $this->nombre, 'email' => $this->correo];
    }
}

<?php

namespace App\Http\Resources\Api\V1\Teacher;

use App\Models\Nota;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Nota */
class GradeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->getKey(), 'type' => $this->tipo, 'value' => $this->valor, 'registered_at' => $this->fecha_registro->toDateString()];
    }
}

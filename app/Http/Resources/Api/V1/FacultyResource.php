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
        ];
    }
}

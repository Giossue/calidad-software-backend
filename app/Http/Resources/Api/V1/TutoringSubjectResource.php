<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Subject */
class TutoringSubjectResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'career_id' => $this->career_id,
            'career_name' => $this->whenLoaded('career', fn () => $this->career->nombre),
            'code' => $this->code,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'cycle_ids' => $this->whenLoaded('cycles', fn () => $this->cycles->pluck('id_ciclo')->all()),
        ];
    }
}

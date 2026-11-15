<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Malla;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Malla */
class MallaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'career_id' => $this->career_id,
            'name' => $this->name,
            'is_current' => $this->is_current,
            'subjects_count' => (int) ($this->subjects_count ?? 0),
            'students_count' => (int) ($this->students_count ?? 0),
            'tutorings_count' => (int) ($this->tutorings_count ?? 0),
        ];
    }
}

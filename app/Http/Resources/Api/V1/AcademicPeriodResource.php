<?php

namespace App\Http\Resources\Api\V1;

use App\Models\PeriodoAcademico;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PeriodoAcademico */
class AcademicPeriodResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'name' => $this->nombre,
            'start_date' => $this->fecha_inicio->toDateString(),
            'end_date' => $this->fecha_fin->toDateString(),
            'is_active' => $this->estado,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

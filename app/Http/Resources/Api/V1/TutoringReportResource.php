<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Reporte;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Reporte */
class TutoringReportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'type' => $this->tipo_reporte,
            'author_id' => $this->fk_id_usuario,
            'author_name' => $this->whenLoaded('generadoPor', fn () => $this->generadoPor->nombre),
            'generated_at' => $this->fecha_generacion->toISOString(),
            'content' => $this->content,
        ];
    }
}

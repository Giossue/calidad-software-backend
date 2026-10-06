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
        $career = $this->carrera
            ?? ($this->relationLoaded('coordinatedCareers') ? $this->coordinatedCareers->first() : null)
            ?? ($this->relationLoaded('teachingCareers') ? $this->teachingCareers->first() : null);

        return [
            'id' => $this->getKey(),
            'identification' => $this->cedula,
            'name' => $this->nombre,
            'email' => $this->correo,
            'phone' => $this->telefono,
            'role' => $this->roles->first()?->slug,
            'coordinated_career_ids' => $this->whenLoaded('coordinatedCareers', fn () => $this->coordinatedCareers->pluck('id_carrera')->all()),
            'career_id' => $this->fk_carrera ?? $career?->id_carrera,
            'career_name' => $career?->nombre,
            'faculty_id' => $career?->fk_facultad,
            'faculty_name' => $career?->facultad?->nombre,
            'cycle_number' => $this->ciclo_actual,
            // 'tutorias' (ciclos anteriores) o 'titulacion' (último ciclo de la carrera).
            'academic_stage' => $this->academicStage(),
            'is_active' => $this->estado,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'has_two_factor' => $this->two_factor_confirmed_at !== null,
        ];
    }
}

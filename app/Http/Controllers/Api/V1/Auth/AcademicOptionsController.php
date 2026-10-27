<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\Carrera;
use App\Models\Ciclo;
use App\Models\Facultad;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * Facultades, carreras y ciclos activos que el estudiante elige al completar su cuenta.
 * Se sirve antes de que el perfil esté completo, por eso vive junto a profile/complete.
 */
class AcademicOptionsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $faculties = Facultad::query()
            ->where('estado', true)
            ->whereHas('carreras', fn (Builder $career) => $career->where('estado', true))
            ->with(['carreras' => fn ($career) => $career->where('estado', true)->orderBy('nombre')
                ->with(['ciclos' => fn ($cycle) => $cycle->where('estado', true)->with('paralelo')->orderBy('numero')->orderBy('id_ciclo')])])
            ->orderBy('nombre')
            ->get();

        return response()->json(['data' => $faculties->map(fn (Facultad $faculty): array => [
            'id' => $faculty->getKey(),
            'name' => $faculty->nombre,
            'careers' => $faculty->carreras->map(fn (Carrera $career): array => [
                'id' => $career->getKey(),
                'name' => $career->nombre,
                'cycles' => $career->ciclos->map(fn (Ciclo $cycle): array => [
                    'id' => $cycle->getKey(),
                    'number' => $cycle->numero,
                    'name' => $cycle->nombre,
                    'section_name' => $cycle->paralelo?->nombre,
                ])->values()->all(),
            ])->values()->all(),
        ])->values()->all()]);
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Coordination;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Coordination\RegisterPeriodSectionRequest;
use App\Http\Resources\Api\V1\AcademicPeriodResource;
use App\Http\Resources\Api\V1\SectionResource;
use App\Models\Paralelo;
use App\Models\PeriodoAcademico;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PeriodSectionController extends Controller
{
    /**
     * Recupera el período académico vigente desde la base de datos (estado = true).
     */
    public function currentPeriod(): JsonResponse|AcademicPeriodResource
    {
        $currentPeriod = PeriodoAcademico::query()->where('estado', true)->first();

        if (! $currentPeriod) {
            return response()->json([
                'message' => 'No existe un período académico vigente actualmente.',
            ], Response::HTTP_NOT_FOUND);
        }

        return AcademicPeriodResource::make($currentPeriod);
    }

    /**
     * Lista los paralelos registrados en el período académico vigente.
     */
    public function index(Request $request): JsonResponse|AnonymousResourceCollection
    {
        $currentPeriod = PeriodoAcademico::query()->where('estado', true)->first();

        if (! $currentPeriod) {
            return response()->json([
                'message' => 'No existe un período académico vigente actualmente.',
            ], Response::HTTP_NOT_FOUND);
        }

        return SectionResource::collection(
            $currentPeriod->paralelos()->orderBy('nombre')->get()
        );
    }

    /**
     * Registra un paralelo y lo vincula al período académico vigente.
     */
    public function store(RegisterPeriodSectionRequest $request): JsonResponse
    {
        $currentPeriod = PeriodoAcademico::query()->where('estado', true)->first();

        if (! $currentPeriod) {
            return response()->json([
                'message' => 'No es posible registrar un paralelo porque no existe un período académico vigente.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var Paralelo $section */
        $section = Paralelo::query()->firstOrCreate(
            ['nombre' => $request->validated('name')],
            ['estado' => true]
        );

        if (! $section->estado) {
            $section->update(['estado' => true]);
        }

        $currentPeriod->paralelos()->syncWithoutDetaching([$section->getKey()]);

        return response()->json([
            'data' => [
                'id' => $section->getKey(),
                'name' => $section->nombre,
                'status' => $section->estado,
                'academic_period' => [
                    'id' => $currentPeriod->getKey(),
                    'name' => $currentPeriod->nombre,
                ],
            ],
            'message' => 'Paralelo registrado exitosamente para el período académico vigente.',
        ], Response::HTTP_CREATED);
    }
}

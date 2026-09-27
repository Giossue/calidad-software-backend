<?php

namespace App\Http\Controllers\Api\V1\Tutoring;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AcademicPeriodResource;
use App\Http\Resources\Api\V1\CareerResource;
use App\Http\Resources\Api\V1\CycleResource;
use App\Http\Resources\Api\V1\ModalityResource;
use App\Models\Carrera;
use App\Models\Ciclo;
use App\Models\Modalidad;
use App\Models\PeriodoAcademico;
use App\Models\Subject;
use App\Support\TutoringCoordinatorAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CatalogController extends Controller
{
    public function careers(Request $request, TutoringCoordinatorAccess $access): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Subject::class);

        return CareerResource::collection($access->scopeCareer(Carrera::query()->where('estado', true), $request->user(), 'id_carrera')
            ->orderBy('nombre')->get());
    }

    public function cycles(Request $request, TutoringCoordinatorAccess $access): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Subject::class);

        return CycleResource::collection($access->scopeCareer(Ciclo::query()->where('estado', true)
            ->whereHas('carrera', fn ($q) => $q->where('estado', true))->with('carrera', 'paralelo'), $request->user())
            ->orderBy('fk_carrera')->orderBy('numero')->get());
    }

    public function periods(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Subject::class);

        return AcademicPeriodResource::collection(PeriodoAcademico::query()->where('estado', true)->orderByDesc('fecha_inicio')->get());
    }

    public function modalities(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Subject::class);

        return ModalityResource::collection(Modalidad::query()->where('estado', true)->orderBy('nombre')->get());
    }
}

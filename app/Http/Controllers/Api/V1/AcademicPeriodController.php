<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\AcademicPeriods\ActivateAcademicPeriod;
use App\Actions\AcademicPeriods\DeactivateAcademicPeriod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AcademicPeriods\StoreAcademicPeriodRequest;
use App\Http\Requests\Api\V1\AcademicPeriods\UpdateAcademicPeriodRequest;
use App\Http\Resources\Api\V1\AcademicPeriodResource;
use App\Models\PeriodoAcademico;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AcademicPeriodController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PeriodoAcademico::class);

        return AcademicPeriodResource::collection(
            PeriodoAcademico::query()->orderByDesc('fecha_inicio')->orderBy('id_periodo')->paginate(),
        );
    }

    public function store(StoreAcademicPeriodRequest $request): JsonResponse
    {
        $academicPeriod = PeriodoAcademico::query()->create($request->validated());

        return AcademicPeriodResource::make($academicPeriod)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateAcademicPeriodRequest $request, PeriodoAcademico $academicPeriod): AcademicPeriodResource
    {
        $academicPeriod->update($request->validated());

        return AcademicPeriodResource::make($academicPeriod->refresh());
    }

    public function deactivate(PeriodoAcademico $academicPeriod, DeactivateAcademicPeriod $deactivateAcademicPeriod): AcademicPeriodResource
    {
        $this->authorize('deactivate', $academicPeriod);

        return AcademicPeriodResource::make($deactivateAcademicPeriod->handle($academicPeriod));
    }

    public function activate(PeriodoAcademico $academicPeriod, ActivateAcademicPeriod $activateAcademicPeriod): AcademicPeriodResource
    {
        $this->authorize('activate', $academicPeriod);

        return AcademicPeriodResource::make($activateAcademicPeriod->handle($academicPeriod));
    }
}

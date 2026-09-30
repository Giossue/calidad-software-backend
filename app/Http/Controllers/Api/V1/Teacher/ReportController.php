<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Actions\Teacher\SendTutoringReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Teacher\ReportRequest;
use App\Http\Requests\Api\V1\Teacher\TeacherListRequest;
use App\Http\Resources\Api\V1\TutoringReportResource;
use App\Models\AsignaturaTutoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReportController extends Controller
{
    public function index(TeacherListRequest $request, AsignaturaTutoria $tutoring): AnonymousResourceCollection
    {
        return TutoringReportResource::collection($tutoring->reportes()->with('generadoPor')->orderByDesc('fecha_generacion')->orderByDesc('id_reporte')->paginate($request->integer('per_page', 15)));
    }

    public function store(ReportRequest $request, AsignaturaTutoria $tutoring, SendTutoringReport $action): JsonResponse
    {
        return TutoringReportResource::make($action->execute($request->user(), $tutoring, $request->validated()))->response()->setStatusCode(201);
    }
}

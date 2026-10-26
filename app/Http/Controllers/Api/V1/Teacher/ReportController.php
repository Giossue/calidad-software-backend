<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Actions\Teacher\DiagnosticEvaluationReport;
use App\Actions\Teacher\FinalPaoReport;
use App\Actions\Teacher\SendTutoringReport;
use App\Actions\Teacher\TutoringFollowUpReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Teacher\DiagnosticReportRequest;
use App\Http\Requests\Api\V1\Teacher\FinalPaoReportPreviewRequest;
use App\Http\Requests\Api\V1\Teacher\FinalPaoReportRequest;
use App\Http\Requests\Api\V1\Teacher\FollowUpReportPreviewRequest;
use App\Http\Requests\Api\V1\Teacher\FollowUpReportRequest;
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

    public function previewDiagnostic(TeacherListRequest $request, AsignaturaTutoria $tutoring, DiagnosticEvaluationReport $report): JsonResponse
    {
        $date = $request->validated('date') ?? today()->toDateString();

        return response()->json(['data' => $report->build($request->user(), $tutoring, $date)]);
    }

    public function storeDiagnostic(DiagnosticReportRequest $request, AsignaturaTutoria $tutoring, DiagnosticEvaluationReport $report): JsonResponse
    {
        return TutoringReportResource::make($report->send($request->user(), $tutoring, $request->validated()))->response()->setStatusCode(201);
    }

    public function previewFollowUp(FollowUpReportPreviewRequest $request, AsignaturaTutoria $tutoring, TutoringFollowUpReport $report): JsonResponse
    {
        return response()->json(['data' => $report->build($request->user(), $tutoring, $this->followUpInput($request->validated()))]);
    }

    public function storeFollowUp(FollowUpReportRequest $request, AsignaturaTutoria $tutoring, TutoringFollowUpReport $report): JsonResponse
    {
        return TutoringReportResource::make($report->send($request->user(), $tutoring, $this->followUpInput($request->validated())))->response()->setStatusCode(201);
    }

    public function previewFinalPao(FinalPaoReportPreviewRequest $request, AsignaturaTutoria $tutoring, FinalPaoReport $report): JsonResponse
    {
        $data = $this->finalPaoInput($request->validated());
        if (! $request->has('tutoring_ids')) {
            $data['tutoring_ids'] = array_values(array_map(fn (mixed $id): int => (int) $id, FinalPaoReport::eligibleTutorings($request->user(), $tutoring)->modelKeys()));
        }

        return response()->json(['data' => $report->build($request->user(), $tutoring, $data)]);
    }

    public function storeFinalPao(FinalPaoReportRequest $request, AsignaturaTutoria $tutoring, FinalPaoReport $report): JsonResponse
    {
        return TutoringReportResource::make($report->send($request->user(), $tutoring, $this->finalPaoInput($request->validated())))->response()->setStatusCode(201);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{date: string, report_number: string|null, tutoring_ids: list<int>, observations: array<int|string, string|null>, conclusions: string|null, recommendations: string|null}
     */
    private function finalPaoInput(array $validated): array
    {
        return [
            'date' => (string) $validated['date'],
            'report_number' => isset($validated['report_number']) ? (string) $validated['report_number'] : null,
            'tutoring_ids' => array_values(array_map(fn (mixed $id): int => (int) $id, (array) ($validated['tutoring_ids'] ?? []))),
            'observations' => (array) ($validated['observations'] ?? []),
            'conclusions' => isset($validated['conclusions']) ? (string) $validated['conclusions'] : null,
            'recommendations' => isset($validated['recommendations']) ? (string) $validated['recommendations'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{partial: int, from: string, to: string, date: string, observations: string|null}
     */
    private function followUpInput(array $validated): array
    {
        return [
            'partial' => (int) $validated['partial'],
            'from' => (string) $validated['from'],
            'to' => (string) $validated['to'],
            'date' => (string) $validated['date'],
            'observations' => isset($validated['observations']) ? (string) $validated['observations'] : null,
        ];
    }
}

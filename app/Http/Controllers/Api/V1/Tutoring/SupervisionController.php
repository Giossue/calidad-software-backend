<?php

namespace App\Http\Controllers\Api\V1\Tutoring;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TutoringAttendanceResource;
use App\Http\Resources\Api\V1\TutoringReportResource;
use App\Models\AsignaturaTutoria;
use App\Models\Asistencia;
use App\Models\Reporte;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SupervisionController extends Controller
{
    public function attendance(Request $request, AsignaturaTutoria $tutoring): AnonymousResourceCollection
    {
        Gate::authorize('view', $tutoring);

        return TutoringAttendanceResource::collection(Asistencia::query()
            ->whereHas('inscripcion', fn (Builder $q) => $q->where('fk_asig_tutoria', $tutoring->getKey()))
            ->with('estudiante')->orderByDesc('fecha')->paginate(min(max($request->integer('per_page', 20), 1), 100)));
    }

    public function reports(Request $request, AsignaturaTutoria $tutoring): AnonymousResourceCollection
    {
        Gate::authorize('view', $tutoring);

        $attendance = Asistencia::query()
            ->whereHas('inscripcion', fn (Builder $q) => $q->where('fk_asig_tutoria', $tutoring->getKey()));

        return TutoringReportResource::collection(Reporte::query()
            ->where('fk_asig_tutoria', $tutoring->getKey())
            ->with('generadoPor')->orderByDesc('fecha_generacion')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100)))
            ->additional(['meta' => [
                'enrollment_count' => $tutoring->inscripciones()->count(),
                'present_count' => (clone $attendance)->where('estado_asistencia', true)->count(),
                'absent_count' => (clone $attendance)->where('estado_asistencia', false)->count(),
            ]]);
    }
}

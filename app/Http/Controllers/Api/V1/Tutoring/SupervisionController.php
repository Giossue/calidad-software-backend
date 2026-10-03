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
            ->with('estudiante', 'session')->orderByDesc('fecha')->paginate(min(max($request->integer('per_page', 20), 1), 100)));
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

    public function allReports(Request $request, \App\Support\TutoringCoordinatorAccess $access): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', AsignaturaTutoria::class);

        $query = Reporte::query()
            ->with(['generadoPor', 'asignaturaTutoria.ciclo.carrera', 'asignaturaTutoria.paralelo'])
            ->whereHas('asignaturaTutoria.ciclo', fn (Builder $q) => $access->scopeCareer($q, $request->user()));

        if ($tutoringId = $request->integer('tutoring_id')) {
            $query->where('fk_asig_tutoria', $tutoringId);
        }

        if ($careerId = $request->integer('career_id')) {
            $query->whereHas('asignaturaTutoria.ciclo', fn (Builder $q) => $q->where('fk_carrera', $careerId));
        }

        if ($cycleId = $request->integer('cycle_id')) {
            $query->whereHas('asignaturaTutoria', fn (Builder $q) => $q->where('fk_ciclo', $cycleId));
        }

        if ($search = trim((string) $request->string('search'))) {
            $query->where(function (Builder $sub) use ($search) {
                $sub->whereLike('tipo_reporte', "%{$search}%")
                    ->orWhereLike('content', "%{$search}%")
                    ->orWhereHas('generadoPor', fn (Builder $q) => $q->whereLike('nombre', "%{$search}%")->orWhereLike('apellido', "%{$search}%"))
                    ->orWhereHas('asignaturaTutoria', fn (Builder $q) => $q->whereLike('nombre', "%{$search}%"));
            });
        }

        $allReports = clone $query;

        return TutoringReportResource::collection(
            $query->orderByDesc('fecha_generacion')
                ->paginate(min(max($request->integer('per_page', 15), 1), 100))
        )->additional(['meta' => [
            'enrollment_count' => Asistencia::query()
                ->whereHas('inscripcion.asignaturaTutoria.ciclo', fn (Builder $q) => $access->scopeCareer($q, $request->user()))
                ->distinct('fk_id_usuario')
                ->count('fk_id_usuario'),
            'present_count' => Asistencia::query()
                ->whereHas('inscripcion.asignaturaTutoria.ciclo', fn (Builder $q) => $access->scopeCareer($q, $request->user()))
                ->where('estado_asistencia', true)
                ->count(),
            'absent_count' => Asistencia::query()
                ->whereHas('inscripcion.asignaturaTutoria.ciclo', fn (Builder $q) => $access->scopeCareer($q, $request->user()))
                ->where('estado_asistencia', false)
                ->count(),
        ]]);
    }
}

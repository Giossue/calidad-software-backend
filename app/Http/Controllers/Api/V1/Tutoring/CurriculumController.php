<?php

namespace App\Http\Controllers\Api\V1\Tutoring;

use App\Actions\Tutoring\ManageCurriculum;
use App\Http\Controllers\Controller;
use App\Imports\CurriculumFile;
use App\Models\Curriculum;
use App\Models\PeriodoAcademico;
use App\Models\Subject;
use App\Support\Curricula;
use App\Support\TutoringCoordinatorAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/** Versiones de la malla curricular de una carrera. */
class CurriculumController extends Controller
{
    public function __construct(private TutoringCoordinatorAccess $access) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Subject::class);
        $data = $request->validate(['career_id' => ['required', 'integer', 'exists:carrera,id_carrera']]);
        $careerId = (int) $data['career_id'];
        $this->access->authorizeCareer($request->user(), $careerId);

        $curricula = Curricula::ordered($careerId)->loadCount('subjects');
        $inForce = Curricula::inForceAt($careerId);
        $previous = null;
        $items = [];
        foreach ($curricula as $curriculum) {
            $items[] = [
                'id' => $curriculum->id,
                'name' => $curriculum->name,
                'is_initial' => $curriculum->from_period_id === null,
                'from_period_id' => $curriculum->from_period_id,
                'from_period_name' => $curriculum->fromPeriod?->nombre,
                'up_to_cycle' => $curriculum->up_to_cycle,
                'previous_name' => $curriculum->from_period_id === null ? null : $previous?->name,
                'in_force' => $curriculum->id === $inForce->id,
                'subjects_count' => $curriculum->subjects_count,
            ];
            $previous = $curriculum;
        }

        return response()->json([
            'data' => array_reverse($items),
            // PAO que se pueden elegir para una malla nueva: el vigente y los futuros.
            'periods' => PeriodoAcademico::query()->whereDate('fecha_fin', '>=', today())->orderBy('fecha_inicio')->get()
                ->map(fn (PeriodoAcademico $period): array => ['id' => $period->id_periodo, 'name' => $period->nombre, 'start_date' => $period->fecha_inicio->toDateString()])->values(),
        ]);
    }

    /** Lee el archivo y devuelve las asignaturas para revisarlas antes de crear la malla. */
    public function preview(Request $request, CurriculumFile $reader, ManageCurriculum $action): JsonResponse
    {
        Gate::authorize('create', Subject::class);
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,csv,txt,xlsx', 'max:5120'],
            'career_id' => ['required', 'integer', 'exists:carrera,id_carrera'],
        ], [
            'file.mimes' => 'Sube la malla en Excel (.xlsx), CSV o PDF.',
            'file.max' => 'El archivo no puede superar 5 MB.',
        ], ['file' => 'archivo', 'career_id' => 'carrera']);
        $this->access->authorizeCareer($request->user(), (int) $data['career_id']);

        $file = $request->file('file');
        $rows = $reader->read($file->getRealPath(), $file->getClientOriginalExtension());

        return response()->json(['data' => ['subjects' => $action->check((int) $data['career_id'], $rows)]]);
    }

    public function store(Request $request, ManageCurriculum $action): JsonResponse
    {
        Gate::authorize('create', Subject::class);
        $data = $request->validate([
            'career_id' => ['required', 'integer', 'exists:carrera,id_carrera'],
            'name' => ['required', 'string', 'max:150'],
            'period_id' => ['required', 'integer', 'exists:periodo_academico,id_periodo'],
            'up_to_cycle' => ['required', 'integer', 'min:1', 'max:'.ManageCurriculum::MAX_CYCLE],
            'subjects' => ['required', 'array', 'min:1', 'max:'.CurriculumFile::MAX_ROWS],
            'subjects.*.code' => ['nullable', 'string'],
            'subjects.*.name' => ['nullable', 'string'],
            'subjects.*.cycle' => ['nullable', 'integer'],
            'subjects.*.previous' => ['nullable', 'array'],
            'subjects.*.previous.*' => ['string'],
        ], [], ['name' => 'nombre de la malla', 'period_id' => 'PAO', 'up_to_cycle' => 'ciclo', 'subjects' => 'asignaturas']);

        $curriculum = $action->create($request->user(), [
            'career_id' => (int) $data['career_id'],
            'name' => $data['name'],
            'period_id' => (int) $data['period_id'],
            'up_to_cycle' => (int) $data['up_to_cycle'],
            'subjects' => array_values($data['subjects']),
        ]);

        return response()->json(['data' => ['id' => $curriculum->id, 'name' => $curriculum->name, 'subjects_count' => $curriculum->subjects()->count()]], Response::HTTP_CREATED);
    }

    public function destroy(Request $request, Curriculum $curriculum, ManageCurriculum $action): Response
    {
        Gate::authorize('create', Subject::class);
        $action->delete($request->user(), $curriculum);

        return response()->noContent();
    }
}

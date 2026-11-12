<?php

namespace App\Http\Controllers\Api\V1\Tutoring;

use App\Actions\Tutoring\ManageCurriculum;
use App\Http\Controllers\Controller;
use App\Imports\CurriculumFile;
use App\Models\Subject;
use App\Support\TutoringCoordinatorAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/** Importación de la malla curricular de una carrera (la malla es la carrera). */
class CurriculumController extends Controller
{
    public function __construct(private TutoringCoordinatorAccess $access) {}

    /** Lee el archivo y devuelve las asignaturas para revisarlas antes de importarlas. */
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

    /** Crea en la carrera las asignaturas de la malla revisada en la vista previa. */
    public function store(Request $request, ManageCurriculum $action): JsonResponse
    {
        Gate::authorize('create', Subject::class);
        $data = $request->validate([
            'career_id' => ['required', 'integer', 'exists:carrera,id_carrera'],
            'subjects' => ['required', 'array', 'min:1', 'max:'.CurriculumFile::MAX_ROWS],
            'subjects.*.code' => ['nullable', 'string'],
            'subjects.*.name' => ['nullable', 'string'],
            'subjects.*.cycle' => ['nullable', 'integer'],
            'subjects.*.previous' => ['nullable', 'array'],
            'subjects.*.previous.*' => ['string'],
        ], [], ['career_id' => 'carrera', 'subjects' => 'asignaturas']);

        $created = $action->import($request->user(), [
            'career_id' => (int) $data['career_id'],
            'subjects' => array_values($data['subjects']),
        ]);

        return response()->json(['created' => $created], Response::HTTP_CREATED);
    }
}

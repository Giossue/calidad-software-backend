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
use Illuminate\Validation\Rule;

/** Importación de una malla curricular de una carrera: las asignaturas van a la malla elegida. */
class CurriculumController extends Controller
{
    private const MALLA_MESSAGES = ['malla_id.exists' => 'La malla debe ser de la carrera elegida.'];

    public function __construct(private TutoringCoordinatorAccess $access) {}

    /**
     * La malla elegida debe ser de la carrera indicada.
     *
     * @return array<string, array<int, mixed>>
     */
    private static function mallaRules(Request $request): array
    {
        return ['malla_id' => ['required', 'integer', Rule::exists('malla', 'id')->where('career_id', $request->integer('career_id'))]];
    }

    /** Lee el archivo y devuelve las asignaturas para revisarlas antes de importarlas. */
    public function preview(Request $request, CurriculumFile $reader, ManageCurriculum $action): JsonResponse
    {
        Gate::authorize('create', Subject::class);
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,csv,txt,xlsx', 'max:5120'],
            'career_id' => ['required', 'integer', 'exists:carrera,id_carrera'],
            ...self::mallaRules($request),
        ], [
            'file.mimes' => 'Sube la malla en Excel (.xlsx), CSV o PDF.',
            'file.max' => 'El archivo no puede superar 5 MB.',
            ...self::MALLA_MESSAGES,
        ], ['file' => 'archivo', 'career_id' => 'carrera', 'malla_id' => 'malla']);
        $this->access->authorizeCareer($request->user(), (int) $data['career_id']);

        $file = $request->file('file');
        $rows = $reader->read($file->getRealPath(), $file->getClientOriginalExtension());

        return response()->json(['data' => ['subjects' => $action->check((int) $data['malla_id'], $rows)]]);
    }

    /** Crea en la carrera las asignaturas de la malla revisada en la vista previa. */
    public function store(Request $request, ManageCurriculum $action): JsonResponse
    {
        Gate::authorize('create', Subject::class);
        $data = $request->validate([
            'career_id' => ['required', 'integer', 'exists:carrera,id_carrera'],
            ...self::mallaRules($request),
            'subjects' => ['required', 'array', 'min:1', 'max:'.CurriculumFile::MAX_ROWS],
            'subjects.*.code' => ['nullable', 'string'],
            'subjects.*.name' => ['nullable', 'string'],
            'subjects.*.cycle' => ['nullable', 'integer'],
            'subjects.*.previous' => ['nullable', 'array'],
            'subjects.*.previous.*' => ['string'],
        ], self::MALLA_MESSAGES, ['career_id' => 'carrera', 'malla_id' => 'malla', 'subjects' => 'asignaturas']);

        $created = $action->import($request->user(), [
            'career_id' => (int) $data['career_id'],
            'malla_id' => (int) $data['malla_id'],
            'subjects' => array_values($data['subjects']),
        ]);

        return response()->json(['created' => $created], Response::HTTP_CREATED);
    }
}

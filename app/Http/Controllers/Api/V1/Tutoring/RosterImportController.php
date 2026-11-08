<?php

namespace App\Http\Controllers\Api\V1\Tutoring;

use App\Actions\Tutoring\ImportRoster;
use App\Http\Controllers\Controller;
use App\Imports\RosterPdf;
use App\Models\AsignaturaTutoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Carga el listado PDF de una asignatura. Con `confirm=false` devuelve la vista
 * previa; con `confirm=true` crea la tutoría, las cuentas y las inscripciones.
 */
class RosterImportController extends Controller
{
    public function __invoke(Request $request, RosterPdf $reader, ImportRoster $import): JsonResponse
    {
        Gate::authorize('viewAny', AsignaturaTutoria::class);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'career_id' => ['required', 'integer', 'exists:carrera,id_carrera'],
            'confirm' => ['sometimes', 'boolean'],
        ], [
            'file.mimes' => 'El archivo debe ser el listado en PDF.',
            'file.max' => 'El archivo no puede superar 5 MB.',
        ], ['file' => 'archivo', 'career_id' => 'carrera']);

        $roster = $reader->read($request->file('file')->getRealPath());

        return response()->json(['data' => $import->handle($request->user(), (int) $data['career_id'], $roster, $request->boolean('confirm'))]);
    }
}

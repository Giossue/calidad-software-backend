<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BulkImportResource;
use App\Imports\CsvReader;
use App\Imports\ImporterRegistry;
use App\Jobs\ProcessBulkImport;
use App\Models\BulkImport;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BulkImportController extends Controller
{
    public function template(Request $request, string $type, ImporterRegistry $registry): Response
    {
        $importer = $registry->resolve($type);
        abort_unless($importer->authorize($this->user($request)), 403);

        return response(CsvReader::template($importer->example()), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"plantilla-{$type}.csv\"",
        ]);
    }

    public function store(Request $request, string $type, ImporterRegistry $registry, CsvReader $reader): JsonResponse
    {
        $importer = $registry->resolve($type);
        $user = $this->user($request);
        abort_unless($importer->authorize($user), 403);

        $request->validate(
            ['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']],
            ['file.mimes' => 'El archivo debe ser un CSV.', 'file.max' => 'El archivo no puede superar 2 MB.'],
            ['file' => 'archivo'],
        );

        $rows = $reader->read((string) $request->file('file')->get(), $importer->columns());

        $import = BulkImport::query()->create([
            'type' => $type,
            'user_id' => $user->getKey(),
            'status' => BulkImport::STATUS_PENDING,
            'total_rows' => count($rows),
            'rows' => $rows,
        ]);

        ProcessBulkImport::dispatch($import);

        return BulkImportResource::make($import->refresh())->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function show(Request $request, BulkImport $import): BulkImportResource
    {
        abort_unless($import->user_id === $this->user($request)->getKey(), 404);

        return BulkImportResource::make($import);
    }

    private function user(Request $request): Usuario
    {
        /** @var Usuario */
        return $request->user();
    }
}

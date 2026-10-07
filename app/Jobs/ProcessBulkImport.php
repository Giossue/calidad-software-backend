<?php

namespace App\Jobs;

use App\Imports\ImporterRegistry;
use App\Models\BulkImport;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ProcessBulkImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public BulkImport $import) {}

    public function handle(ImporterRegistry $registry): void
    {
        $import = $this->import;
        $user = $import->user;
        $importer = $registry->resolve($import->type);
        $import->update(['status' => BulkImport::STATUS_PROCESSING]);

        $created = 0;
        $errors = [];
        foreach ($import->rows ?? [] as $index => $row) {
            try {
                if (! $user->estado || ! $importer->authorize($user)) {
                    throw new AuthorizationException;
                }
                DB::transaction(fn () => $importer->import($row['values'], $user));
                $created++;
            } catch (ValidationException $exception) {
                $errors[] = ['row' => $row['line'], 'messages' => array_values(array_unique(Arr::flatten($exception->errors())))];
            } catch (AuthorizationException|HttpExceptionInterface) {
                $errors[] = ['row' => $row['line'], 'messages' => ['No tienes permiso para registrar este elemento.']];
            } catch (Throwable $exception) {
                report($exception);
                $errors[] = ['row' => $row['line'], 'messages' => ['No se pudo registrar la fila por un error inesperado.']];
            }

            if (($index + 1) % 10 === 0) {
                $import->update(['created_count' => $created, 'failed_count' => count($errors), 'errors' => $errors]);
            }
        }

        $import->update([
            'status' => BulkImport::STATUS_DONE,
            'created_count' => $created,
            'failed_count' => count($errors),
            'errors' => $errors,
            'rows' => null,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->import->update(['status' => BulkImport::STATUS_FAILED, 'rows' => null]);
    }
}

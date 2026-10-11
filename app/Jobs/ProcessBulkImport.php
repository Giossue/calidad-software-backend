<?php

namespace App\Jobs;

use App\Imports\ImporterRegistry;
use App\Models\BulkImport;
use App\Support\ActionNotifier;
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

    /** Pantalla a la que lleva el aviso de importación terminada. */
    private const SECTION_URLS = [
        'users' => '/panel/users',
        'faculties' => '/panel/faculties',
        'careers' => '/panel/careers',
        'cycles' => '/panel/careers',
        'academic-periods' => '/panel/periods',
        'subjects' => '/panel/tutoring-subjects',
        'teachers' => '/panel/tutoring-teachers',
        'students' => '/panel/tutoring-students',
        'sections' => '/panel/degree-students',
    ];

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
        $warnings = [];
        foreach ($import->rows ?? [] as $index => $row) {
            try {
                if (! $user->estado || ! $importer->authorize($user)) {
                    throw new AuthorizationException;
                }
                DB::transaction(fn () => $importer->import($row['values'], $user));
                $created++;
            } catch (ValidationException $exception) {
                $entry = ['row' => $row['line'], 'messages' => array_values(array_unique(Arr::flatten($exception->errors())))];
                if (self::isAlreadyRegistered($exception)) {
                    $warnings[] = $entry;
                } else {
                    $errors[] = $entry;
                }
            } catch (AuthorizationException|HttpExceptionInterface) {
                $errors[] = ['row' => $row['line'], 'messages' => ['No tienes permiso para registrar este elemento.']];
            } catch (Throwable $exception) {
                report($exception);
                $errors[] = ['row' => $row['line'], 'messages' => ['No se pudo registrar la fila por un error inesperado.']];
            }

            if (($index + 1) % 10 === 0) {
                $import->update(['created_count' => $created, 'skipped_count' => count($warnings), 'failed_count' => count($errors), 'errors' => $errors, 'warnings' => $warnings]);
            }
        }

        $import->update([
            'status' => BulkImport::STATUS_DONE,
            'created_count' => $created,
            'skipped_count' => count($warnings),
            'failed_count' => count($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'rows' => null,
        ]);

        ActionNotifier::send($user, 'bulk_import_finished', 'Carga masiva terminada', sprintf(
            '%d %s, %d ya %s y %d con errores.',
            $created, $created === 1 ? 'registro creado' : 'registros creados',
            count($warnings), count($warnings) === 1 ? 'estaba registrado' : 'estaban registrados',
            count($errors),
        ), self::SECTION_URLS[$import->type] ?? null, includeActor: true);
    }

    /**
     * La fila solo falló porque sus datos ya existen en el sistema (reglas unique):
     * se informa como aviso y no como error.
     */
    private static function isAlreadyRegistered(ValidationException $exception): bool
    {
        $failedRules = collect($exception->validator->failed())->flatMap(fn (array $rules): array => array_keys($rules));

        return $failedRules->isNotEmpty() && $failedRules->every(fn (string $rule): bool => $rule === 'Unique');
    }

    public function failed(?Throwable $exception): void
    {
        $this->import->update(['status' => BulkImport::STATUS_FAILED, 'rows' => null]);
    }
}

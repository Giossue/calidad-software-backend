<?php

namespace App\Actions\Tutoring;

use App\Http\Requests\Api\V1\Tutoring\SubjectRequest;
use App\Imports\CatalogLookup;
use App\Models\Ciclo;
use App\Models\Subject;
use App\Models\Usuario;
use App\Support\TutoringCoordinatorAccess;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Importa la malla curricular de una carrera: la malla es la carrera, así que
 * subirla crea sus asignaturas, sus ciclos y sus previas en un solo paso.
 */
class ManageCurriculum
{
    public const MAX_CYCLE = 12;

    public function __construct(private TutoringCoordinatorAccess $access) {}

    /**
     * Normaliza y valida las asignaturas leídas del archivo o editadas en la
     * vista previa. Un código o un nombre que ya existe en la carrera es un error;
     * una previa puede ser otra fila o una asignatura que la carrera ya tiene.
     *
     * @param  list<array{code?: string|null, name?: string|null, cycle?: int|string|null, previous?: list<string>|string|null}>  $rows
     * @return list<array{code: string|null, name: string, cycle: int|null, previous: list<string>, errors: list<string>}>
     */
    public function check(int $careerId, array $rows): array
    {
        $existing = $this->existingSubjects($careerId);
        $existingCodes = $existing->pluck('code')->filter()->map(fn (string $code): string => mb_strtoupper($code))->values()->all();
        $existingNames = $existing->map(fn (Subject $subject): string => CatalogLookup::normalize($subject->name))->values()->all();

        $rows = array_map(function (array $row): array {
            $code = trim((string) ($row['code'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            $previous = $row['previous'] ?? [];
            $cycle = $row['cycle'] ?? null;

            return [
                'code' => $code !== '' ? mb_strtoupper($code) : null,
                'name' => $name !== '' ? SubjectRequest::normalizeName($name) : '',
                'cycle' => is_numeric($cycle) ? (int) $cycle : null,
                'previous' => array_values(array_unique(array_filter(array_map(fn ($item): string => trim((string) $item), is_array($previous) ? $previous : (preg_split('/[|;]+/', (string) $previous) ?: [])), fn (string $item): bool => $item !== ''))),
                'errors' => [],
            ];
        }, array_values($rows));

        $codes = array_count_values(array_filter(array_column($rows, 'code')));
        $names = array_count_values(array_map(fn (string $name): string => CatalogLookup::normalize($name), array_filter(array_column($rows, 'name'))));

        foreach ($rows as &$row) {
            if ($row['name'] === '') {
                $row['errors'][] = 'Falta el nombre de la asignatura.';
            } elseif (mb_strlen($row['name']) > 150) {
                $row['errors'][] = 'El nombre no puede superar 150 caracteres.';
            } elseif ($names[CatalogLookup::normalize($row['name'])] > 1) {
                $row['errors'][] = 'El nombre está repetido en la malla.';
            } elseif (in_array(CatalogLookup::normalize($row['name']), $existingNames, true)) {
                $row['errors'][] = 'La asignatura ya existe en la carrera.';
            }
            if ($row['code'] !== null && mb_strlen($row['code']) > 30) {
                $row['errors'][] = 'El código no puede superar 30 caracteres.';
            } elseif ($row['code'] !== null && $codes[$row['code']] > 1) {
                $row['errors'][] = 'El código está repetido en la malla.';
            } elseif ($row['code'] !== null && in_array($row['code'], $existingCodes, true)) {
                $row['errors'][] = 'El código ya existe en la carrera.';
            }
            if ($row['cycle'] === null || $row['cycle'] < 1 || $row['cycle'] > self::MAX_CYCLE) {
                $row['errors'][] = 'Indica el ciclo (1 a '.self::MAX_CYCLE.').';
            }
            foreach ($row['previous'] as $reference) {
                $previous = $this->find($rows, $reference);
                $previousCycle = $previous !== null ? $previous['cycle'] : $this->existingCycle($existing, $reference);
                if ($previous === null && $previousCycle === false) {
                    $row['errors'][] = "La previa «{$reference}» no está en la malla.";
                } elseif ($row['cycle'] !== null && is_int($previousCycle) && $previousCycle >= $row['cycle']) {
                    $row['errors'][] = "La previa «{$reference}» debe ser de un ciclo anterior.";
                }
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Crea en la carrera las asignaturas de la malla, con sus ciclos y previas.
     *
     * @param  array{career_id: int, subjects: list<array<string, mixed>>}  $data
     * @return int asignaturas creadas
     */
    public function import(Usuario $user, array $data): int
    {
        $this->access->authorizeCareer($user, $data['career_id']);
        $rows = $this->check($data['career_id'], $data['subjects']);
        $invalid = array_filter($rows, fn (array $row): bool => $row['errors'] !== []);
        if ($invalid !== []) {
            throw ValidationException::withMessages(['subjects' => count($invalid) === 1 ? 'Hay 1 asignatura con errores; corrígela antes de importar la malla.' : 'Hay '.count($invalid).' asignaturas con errores; corrígelas antes de importar la malla.']);
        }

        // Las asignaturas de la malla llevan la modalidad de la carrera.
        $modalityId = ManageSubject::careerModality($data['career_id']);

        return DB::transaction(function () use ($data, $rows, $modalityId): int {
            $existing = $this->existingSubjects($data['career_id']);

            $created = [];
            foreach ($rows as $row) {
                $subject = Subject::query()->create([
                    'career_id' => $data['career_id'],
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'cycle_number' => (int) $row['cycle'],
                    'modality_id' => $modalityId,
                    'is_active' => true,
                ]);
                $this->ensureCycle($data['career_id'], (int) $row['cycle']);
                $created[] = ['row' => $row, 'subject' => $subject];
            }

            $match = function (string $reference) use ($created, $existing): ?Subject {
                foreach ($created as $item) {
                    if ($this->matches($item['row']['code'], $item['row']['name'], $reference)) {
                        return $item['subject'];
                    }
                }

                return $existing->first(fn (Subject $subject): bool => $this->matches($subject->code, $subject->name, $reference));
            };
            foreach ($created as $item) {
                foreach ($item['row']['previous'] as $reference) {
                    $match($reference)?->nextSubjects()->syncWithoutDetaching([$item['subject']->id]);
                }
            }

            return count($created);
        });
    }

    /** @return EloquentCollection<int, Subject> */
    private function existingSubjects(int $careerId): EloquentCollection
    {
        return Subject::query()->where('career_id', $careerId)->with('cycles:ciclo.id_ciclo,ciclo.numero')->orderBy('id')->get(['id', 'code', 'name', 'cycle_number']);
    }

    private function matches(?string $code, string $name, string $reference): bool
    {
        return ($code !== null && mb_strtoupper($code) === mb_strtoupper($reference))
            || ($name !== '' && CatalogLookup::normalize($name) === CatalogLookup::normalize($reference));
    }

    /**
     * @param  list<array{code: string|null, name: string, cycle: int|null, previous: list<string>, errors: list<string>}>  $rows
     * @return array{code: string|null, name: string, cycle: int|null, previous: list<string>, errors: list<string>}|null
     */
    private function find(array $rows, string $reference): ?array
    {
        foreach ($rows as $row) {
            if ($this->matches($row['code'], $row['name'], $reference)) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Ciclo de una asignatura que la carrera ya tiene: `false` si no existe y
     * `null` si no está en ningún ciclo.
     *
     * @param  EloquentCollection<int, Subject>  $existing
     */
    private function existingCycle(EloquentCollection $existing, string $reference): int|false|null
    {
        $subject = $existing->first(fn (Subject $subject): bool => $this->matches($subject->code, $subject->name, $reference));
        if ($subject === null) {
            return false;
        }

        return $subject->cycleNumber();
    }

    /** La carrera tiene el ciclo de ese número; si aún no, se crea sin paralelo. */
    private function ensureCycle(int $careerId, int $number): void
    {
        if (! Ciclo::query()->where('fk_carrera', $careerId)->where('numero', $number)->exists()) {
            Ciclo::query()->create([
                'fk_carrera' => $careerId, 'numero' => $number, 'nombre' => "Ciclo {$number}", 'fk_paralelo' => null, 'estado' => true,
            ]);
        }
    }
}

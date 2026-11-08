<?php

namespace App\Actions\Tutoring;

use App\Http\Requests\Api\V1\Tutoring\SubjectRequest;
use App\Imports\CatalogLookup;
use App\Models\AsignaturaTutoria;
use App\Models\Carrera;
use App\Models\Ciclo;
use App\Models\Curriculum;
use App\Models\PeriodoAcademico;
use App\Models\Subject;
use App\Models\Usuario;
use App\Support\Curricula;
use App\Support\TutoringCoordinatorAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sube una malla curricular nueva: crea sus asignaturas y secuencias y decide
 * qué estudiantes la siguen (ver {@see Curricula}).
 */
class ManageCurriculum
{
    public const MAX_CYCLE = 12;

    public function __construct(private TutoringCoordinatorAccess $access) {}

    /**
     * Normaliza y valida las asignaturas leídas del archivo o editadas en la
     * vista previa. Completa el nombre de los códigos que ya existen en la carrera.
     *
     * @param  list<array{code?: string|null, name?: string|null, cycle?: int|string|null, previous?: list<string>|string|null}>  $rows
     * @return list<array{code: string|null, name: string, cycle: int|null, previous: list<string>, errors: list<string>}>
     */
    public function check(int $careerId, array $rows): array
    {
        $known = Subject::query()->where('career_id', $careerId)->whereNotNull('code')->orderByDesc('id')->get(['code', 'name'])
            ->unique(fn (Subject $subject): string => mb_strtoupper((string) $subject->code))
            ->mapWithKeys(fn (Subject $subject): array => [mb_strtoupper((string) $subject->code) => $subject->name]);

        $rows = array_map(function (array $row) use ($known): array {
            $code = trim((string) ($row['code'] ?? ''));
            $code = $code !== '' ? mb_strtoupper($code) : null;
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '' && $code !== null) {
                $name = (string) ($known[$code] ?? '');
            }
            $previous = $row['previous'] ?? [];
            $cycle = $row['cycle'] ?? null;

            return [
                'code' => $code,
                'name' => $name !== '' ? SubjectRequest::normalizeName($name) : '',
                'cycle' => is_numeric($cycle) ? (int) $cycle : null,
                'previous' => array_values(array_unique(array_filter(array_map(fn ($item): string => trim((string) $item), is_array($previous) ? $previous : (preg_split('/[|;]+/', (string) $previous) ?: [])), fn (string $item): bool => $item !== ''))),
                'errors' => [],
            ];
        }, array_values($rows));

        $codes = array_count_values(array_filter(array_column($rows, 'code')));
        $names = array_count_values(array_map(fn (string $name): string => CatalogLookup::normalize($name), array_filter(array_column($rows, 'name'))));
        $find = function (string $reference) use ($rows): ?array {
            $upper = mb_strtoupper($reference);
            $normalized = CatalogLookup::normalize($reference);
            foreach ($rows as $row) {
                if ($row['code'] === $upper || ($row['name'] !== '' && CatalogLookup::normalize($row['name']) === $normalized)) {
                    return $row;
                }
            }

            return null;
        };

        foreach ($rows as &$row) {
            if ($row['name'] === '') {
                $row['errors'][] = 'Falta el nombre de la asignatura.';
            } elseif (mb_strlen($row['name']) > 150) {
                $row['errors'][] = 'El nombre no puede superar 150 caracteres.';
            } elseif ($names[CatalogLookup::normalize($row['name'])] > 1) {
                $row['errors'][] = 'El nombre está repetido en la malla.';
            }
            if ($row['code'] !== null && mb_strlen($row['code']) > 30) {
                $row['errors'][] = 'El código no puede superar 30 caracteres.';
            } elseif ($row['code'] !== null && $codes[$row['code']] > 1) {
                $row['errors'][] = 'El código está repetido en la malla.';
            }
            if ($row['cycle'] === null || $row['cycle'] < 1 || $row['cycle'] > self::MAX_CYCLE) {
                $row['errors'][] = 'Indica el ciclo (1 a '.self::MAX_CYCLE.').';
            }
            foreach ($row['previous'] as $reference) {
                $previous = $find($reference);
                if ($previous === null) {
                    $row['errors'][] = "La previa «{$reference}» no está en la malla.";
                } elseif ($row['cycle'] !== null && $previous['cycle'] !== null && $previous['cycle'] >= $row['cycle']) {
                    $row['errors'][] = "La previa «{$reference}» debe ser de un ciclo anterior.";
                }
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * @param  array{career_id: int, name: string, period_id: int, up_to_cycle: int, subjects: list<array<string, mixed>>}  $data
     */
    public function create(Usuario $user, array $data): Curriculum
    {
        $this->access->authorizeCareer($user, $data['career_id']);
        $period = PeriodoAcademico::query()->findOrFail($data['period_id']);
        if ($period->fecha_fin->toDateString() < today()->toDateString()) {
            throw ValidationException::withMessages(['period_id' => 'Elige el PAO vigente o uno futuro.']);
        }
        $rows = $this->check($data['career_id'], $data['subjects']);
        $invalid = array_filter($rows, fn (array $row): bool => $row['errors'] !== []);
        if ($invalid !== []) {
            throw ValidationException::withMessages(['subjects' => count($invalid) === 1 ? 'Hay 1 asignatura con errores; corrígela antes de crear la malla.' : 'Hay '.count($invalid).' asignaturas con errores; corrígelas antes de crear la malla.']);
        }

        return DB::transaction(function () use ($user, $data, $period, $rows): Curriculum {
            $previous = Curricula::inForceAt($data['career_id'], $period->fecha_inicio);
            $curriculum = Curriculum::query()->create([
                'career_id' => $data['career_id'],
                'name' => trim($data['name']),
                'from_period_id' => $period->getKey(),
                'up_to_cycle' => $data['up_to_cycle'],
                'created_by' => $user->getKey(),
            ]);
            $modalityId = Carrera::query()->whereKey($data['career_id'])->value('fk_modalidad');

            $created = [];
            foreach ($rows as $row) {
                $subject = Subject::query()->create([
                    'career_id' => $data['career_id'],
                    'curriculum_id' => $curriculum->id,
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'modality_id' => $modalityId,
                    'is_active' => true,
                ]);
                $subject->cycles()->attach($this->cycleIds($data['career_id'], (int) $row['cycle']));
                $created[] = ['row' => $row, 'subject' => $subject];
            }

            $match = function (string $reference) use ($created): ?Subject {
                foreach ($created as $item) {
                    if ($item['row']['code'] === mb_strtoupper($reference) || CatalogLookup::normalize($item['row']['name']) === CatalogLookup::normalize($reference)) {
                        return $item['subject'];
                    }
                }

                return null;
            };
            foreach ($created as $item) {
                foreach ($item['row']['previous'] as $reference) {
                    $match($reference)?->nextSubjects()->syncWithoutDetaching([$item['subject']->id]);
                }
            }

            Curricula::assignStudents($curriculum, $previous);

            return $curriculum;
        });
    }

    /** Se puede borrar una malla subida por error mientras ninguna tutoría use sus asignaturas. */
    public function delete(Usuario $user, Curriculum $curriculum): void
    {
        $this->access->authorizeCareer($user, $curriculum->career_id);
        if ($curriculum->from_period_id === null) {
            throw ValidationException::withMessages(['curriculum' => 'La malla inicial no se puede eliminar.']);
        }
        $subjectIds = $curriculum->subjects()->pluck('id');
        if (AsignaturaTutoria::query()->whereIn('subject_id', $subjectIds)->exists()) {
            throw ValidationException::withMessages(['curriculum' => 'No puedes eliminar esta malla: ya hay tutorías de sus asignaturas.']);
        }

        DB::transaction(function () use ($curriculum, $subjectIds): void {
            DB::table('subject_sequences')->whereIn('subject_id', $subjectIds)->orWhereIn('next_subject_id', $subjectIds)->delete();
            DB::table('subject_cycle')->whereIn('subject_id', $subjectIds)->delete();
            DB::table('student_pending_subjects')->whereIn('subject_id', $subjectIds)->delete();
            Subject::query()->whereKey($subjectIds)->delete();
            $curriculum->delete();
        });
    }

    /**
     * Ciclos (uno por paralelo) del número dado; si la carrera aún no lo tiene, lo crea.
     *
     * @return list<int>
     */
    private function cycleIds(int $careerId, int $number): array
    {
        $ids = Ciclo::query()->where('fk_carrera', $careerId)->where('numero', $number)->where('estado', true)->pluck('id_ciclo')->map(fn ($id): int => (int) $id)->all();

        return $ids !== [] ? array_values($ids) : [(int) Ciclo::query()->create([
            'fk_carrera' => $careerId, 'numero' => $number, 'nombre' => "Ciclo {$number}", 'fk_paralelo' => null, 'estado' => true,
        ])->getKey()];
    }
}

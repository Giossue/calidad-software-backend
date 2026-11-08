<?php

namespace App\Imports\Importers;

use App\Imports\CatalogLookup;
use App\Models\AsignaturaTutoria;
use App\Models\Subject;
use App\Models\Usuario;
use App\Support\InstitutionalEmail;
use App\Support\TutoringCoordinatorAccess;
use Illuminate\Validation\ValidationException;

/**
 * Carga de estudiantes. La columna opcional `asignaturas_pendientes` lista, por
 * código o nombre y separadas por «|», las asignaturas de ciclos anteriores que
 * el estudiante arrastra: no se le inscribe en las que las continúan en la malla.
 * Si el estudiante ya estaba registrado, la fila solo actualiza esa lista.
 */
class StudentsImporter extends AccountImporter
{
    public const PENDING_COLUMN = 'asignaturas_pendientes';

    protected function requiredColumns(): array
    {
        return ['facultad', 'carrera', 'ciclo'];
    }

    protected function extraColumns(): array
    {
        return [self::PENDING_COLUMN];
    }

    public function example(): array
    {
        return $this->exampleRow([
            'correo' => 'ana.torres@ueb.edu.ec', 'cedula' => '0201234567', 'nombres' => 'Ana María', 'apellidos' => 'Torres Vera',
            'rol' => 'estudiante', 'facultad' => 'Ciencias Administrativas', 'carrera' => 'Software', 'ciclo' => '1', 'telefono' => '0991234567',
        ]);
    }

    public function authorize(Usuario $user): bool
    {
        return $user->can('viewAny', AsignaturaTutoria::class);
    }

    /** @param  array<string, string|null>  $row */
    protected function role(array $row): string
    {
        return self::assertRole($row, 'estudiante');
    }

    protected function authorizeCareer(Usuario $user, int $careerId): void
    {
        app(TutoringCoordinatorAccess::class)->authorizeCareer($user, $careerId);
    }

    protected function extraRules(): array
    {
        return ['correo' => [InstitutionalEmail::rule()]];
    }

    protected function afterCreate(Usuario $account, array $row): void
    {
        $account->pendingSubjects()->sync($this->pendingSubjectIds($row[self::PENDING_COLUMN] ?? null, (int) $account->fk_carrera, $account->ciclo?->numero));
    }

    protected function updateExisting(Usuario $account, array $row, ?int $careerId): ?string
    {
        if (($row[self::PENDING_COLUMN] ?? null) === null || ! $account->hasRole('estudiante')) {
            return null;
        }
        if ($account->fk_carrera !== $careerId) {
            throw ValidationException::withMessages(['correo' => "El estudiante {$account->correo} ya está registrado en otra carrera."]);
        }

        $ids = $this->pendingSubjectIds($row[self::PENDING_COLUMN], $careerId, $account->ciclo?->numero);
        $account->pendingSubjects()->sync($ids);

        return sprintf('El estudiante %s ya estaba registrado: se actualizaron sus asignaturas pendientes (%d).', $account->correo, count($ids));
    }

    /**
     * Resuelve la lista de la columna a asignaturas de la carrera, que deben ser
     * de un ciclo anterior al del estudiante.
     *
     * @return list<int>
     */
    private function pendingSubjectIds(?string $value, int $careerId, ?int $studentCycle): array
    {
        $names = collect(preg_split('/\s*[|;]\s*/u', trim((string) $value)) ?: [])->filter(fn (string $name): bool => $name !== '')->unique();
        if ($names->isEmpty()) {
            return [];
        }

        $subjects = Subject::query()->where('career_id', $careerId)->with('cycles:id_ciclo,numero')->get(['id', 'code', 'name']);

        return $names->map(function (string $name) use ($subjects, $studentCycle): int {
            $needle = CatalogLookup::normalize($name);
            $subject = $subjects->first(fn (Subject $subject): bool => $subject->code !== null && CatalogLookup::normalize($subject->code) === $needle)
                ?? $subjects->first(fn (Subject $subject): bool => CatalogLookup::normalize($subject->name) === $needle);
            if ($subject === null) {
                throw ValidationException::withMessages([self::PENDING_COLUMN => "La asignatura pendiente «{$name}» no existe en la carrera indicada."]);
            }

            $level = $subject->cycles->min('numero');
            if ($studentCycle !== null && $level !== null && $level >= $studentCycle) {
                throw ValidationException::withMessages([self::PENDING_COLUMN => "«{$subject->name}» es de {$level}.º ciclo: solo se arrastran asignaturas de ciclos anteriores al {$studentCycle}.º."]);
            }

            return $subject->id;
        })->values()->all();
    }
}

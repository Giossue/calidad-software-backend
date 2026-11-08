<?php

namespace App\Actions\Tutoring;

use App\Actions\Users\CreateAccount;
use App\Imports\CatalogLookup;
use App\Models\AsignaturaTutoria;
use App\Models\Carrera;
use App\Models\InscripcionTutoria;
use App\Models\Modalidad;
use App\Models\Paralelo;
use App\Models\PeriodoAcademico;
use App\Models\Subject;
use App\Models\Usuario;
use App\Notifications\QueuedProvisionalPasswordNotification;
use App\Support\InstitutionalEmail;
use App\Support\PendingSubjects;
use App\Support\StudentCycle;
use App\Support\TutoringCoordinatorAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Carga el listado de una asignatura (PDF del sistema académico) en la tutoría
 * del período vigente: la crea si no existe, le asigna el docente del listado y
 * registra e inscribe a los estudiantes. Sin `$apply` solo describe lo que haría.
 *
 * Estados de cada estudiante: `new` (se crea la cuenta y se inscribe), `enroll`
 * (ya registrado, se inscribe), `enrolled` (ya estaba inscrito), `blocked` (no
 * puede inscribirse) y, al aplicar, `error` si la fila falló.
 */
class ImportRoster
{
    public function __construct(
        private ManageTutoring $tutorings,
        private CreateAccount $accounts,
        private StudentCycle $cycles,
        private TutoringCoordinatorAccess $access,
    ) {}

    /**
     * @param  array{career: string|null, teacher: string|null, subject: string, section: string, students: list<array{number: int, name: string, email: string, phone: string|null}>}  $roster
     * @return array<string, mixed>
     */
    public function handle(Usuario $user, int $careerId, array $roster, bool $apply): array
    {
        $this->access->authorizeCareer($user, $careerId);
        $career = Carrera::query()->findOrFail($careerId);

        $report = [
            'applied' => false,
            'career_name' => $roster['career'],
            'subject_name' => $roster['subject'],
            'section_name' => $roster['section'],
            'teacher_name' => $roster['teacher'],
            'subject' => null,
            'cycle_number' => null,
            'period_name' => null,
            'teacher' => null,
            'tutoring' => null,
            'errors' => [],
            'notes' => [],
            'students' => [],
        ];

        $subject = $this->findSubject($careerId, $roster['subject']);
        $period = PeriodoAcademico::query()->where('estado', true)->orderByDesc('fecha_inicio')->first();
        $section = $this->findByName(Paralelo::query()->where('estado', true)->get(), 'nombre', $roster['section']);
        $baseCycle = $subject?->cycles()->where('ciclo.fk_carrera', $careerId)->where('ciclo.estado', true)->orderBy('ciclo.numero')->first();

        if ($subject === null) {
            $report['errors'][] = "La asignatura «{$roster['subject']}» no está registrada en {$career->nombre}. Regístrala en Asignaturas.";
        } elseif ($baseCycle === null) {
            $report['errors'][] = "Asigna primero «{$subject->name}» a un ciclo en Asignaturas.";
        }
        if ($period === null) {
            $report['errors'][] = 'No hay un período académico activo.';
        }
        if ($section === null) {
            $report['errors'][] = "El paralelo «{$roster['section']}» no existe o está inactivo.";
        }
        $listedCareer = CatalogLookup::normalize((string) $roster['career']);
        if ($roster['career'] !== null && $listedCareer !== CatalogLookup::normalize($career->nombre) && $listedCareer !== 'grupo comun') {
            $report['notes'][] = "El listado es de la carrera «{$roster['career']}»; se cargará en {$career->nombre}.";
        }

        $report['subject'] = $subject === null ? null : ['id' => $subject->id, 'code' => $subject->code, 'name' => $subject->name];
        $report['cycle_number'] = $baseCycle?->numero;
        $report['period_name'] = $period?->nombre;

        $tutoring = $subject && $period && $section ? AsignaturaTutoria::query()
            ->where('subject_id', $subject->id)->where('fk_paralelo', $section->getKey())->where('fk_periodo', $period->getKey())
            ->first() : null;
        if ($tutoring !== null && ! $tutoring->estado) {
            $report['errors'][] = "La tutoría de «{$subject->name}» del paralelo {$section->nombre} está deshabilitada. Habilítala en Tutorías.";
        }
        $report['tutoring'] = ['id' => $tutoring?->getKey(), 'exists' => $tutoring !== null];

        $teacher = $this->findTeacher($careerId, $roster['teacher']);
        $current = $tutoring?->fk_docente ? Usuario::query()->find($tutoring->fk_docente) : null;
        if ($current !== null) {
            $report['teacher'] = ['id' => $current->getKey(), 'name' => $current->nombre, 'assign' => false];
            if ($teacher !== null && $teacher->isNot($current)) {
                $report['notes'][] = "La tutoría ya tiene como docente a {$current->nombre}; se mantiene.";
            }
        } elseif ($teacher !== null) {
            $report['teacher'] = ['id' => $teacher->getKey(), 'name' => $teacher->nombre, 'assign' => true];
        } elseif ($roster['teacher'] !== null) {
            $report['notes'][] = "No se encontró al docente «{$roster['teacher']}» entre los docentes registrados; asígnalo después en Tutorías.";
        }

        $ancestors = $subject ? PendingSubjects::ancestorIds($subject->id) : collect();
        foreach ($roster['students'] as $row) {
            $report['students'][] = [...$row, ...$this->plan($row, $careerId, $tutoring, $ancestors)];
        }

        if (! $apply || $report['errors'] !== []) {
            return $report;
        }

        return DB::transaction(function () use ($report, $user, $subject, $baseCycle, $period, $section, $career, $tutoring, $teacher, $current): array {
            if ($tutoring === null) {
                $tutoring = $this->tutorings->create($user, [
                    'subject_id' => $subject->id,
                    'cycle_id' => $baseCycle->getKey(),
                    'period_id' => $period->getKey(),
                    'modality_id' => $subject->modality_id ?? $career->fk_modalidad ?? (int) Modalidad::query()->where('estado', true)->value('id_modalidad'),
                    'parallel_id' => $section->getKey(),
                    'teacher_id' => $teacher?->getKey(),
                ]);
            } elseif ($current === null && $teacher !== null) {
                try {
                    $tutoring = $this->tutorings->assignTeacher($tutoring, $teacher);
                } catch (ValidationException $exception) {
                    $report['teacher'] = null;
                    $report['notes'][] = 'No se pudo asignar el docente: '.collect($exception->errors())->flatten()->first();
                }
            }
            $tutoring->loadMissing('ciclo');
            $report['tutoring'] = ['id' => $tutoring->getKey(), 'exists' => true];

            foreach ($report['students'] as $index => $row) {
                if (! in_array($row['status'], ['new', 'enroll'], true)) {
                    continue;
                }
                try {
                    DB::transaction(fn () => $this->register($row, $career, $tutoring));
                } catch (Throwable $exception) {
                    if (! $exception instanceof ValidationException) {
                        report($exception);
                    }
                    $report['students'][$index]['status'] = 'error';
                    $report['students'][$index]['message'] = $exception instanceof ValidationException
                        ? (string) collect($exception->errors())->flatten()->first()
                        : 'No se pudo registrar por un error inesperado.';
                }
            }
            $report['applied'] = true;

            return $report;
        });
    }

    /**
     * @param  array{number: int, name: string, email: string, phone: string|null}  $row
     * @param  Collection<int, int>  $ancestors
     * @return array{status: string, message: string|null}
     */
    private function plan(array $row, int $careerId, ?AsignaturaTutoria $tutoring, Collection $ancestors): array
    {
        if (! InstitutionalEmail::isInstitutional($row['email'])) {
            return ['status' => 'blocked', 'message' => InstitutionalEmail::message()];
        }
        $student = Usuario::query()->where('correo', $row['email'])->first();
        if ($student === null) {
            return ['status' => 'new', 'message' => null];
        }
        if (! $student->hasRole('estudiante')) {
            return ['status' => 'blocked', 'message' => 'La cuenta con este correo no es de estudiante.'];
        }
        if (! $student->estado) {
            return ['status' => 'blocked', 'message' => 'La cuenta del estudiante está deshabilitada.'];
        }
        if ($student->fk_carrera !== null && $student->fk_carrera !== $careerId) {
            return ['status' => 'blocked', 'message' => 'El estudiante está registrado en otra carrera.'];
        }
        if ($this->cycles->isDegreeStudent($student)) {
            return ['status' => 'blocked', 'message' => 'El estudiante está en titulación y no se inscribe en tutorías.'];
        }
        if ($ancestors->isNotEmpty()) {
            $pending = $student->pendingSubjects()->whereIn('subjects.id', $ancestors)->pluck('subjects.name');
            if ($pending->isNotEmpty()) {
                return ['status' => 'blocked', 'message' => 'Arrastra '.$pending->map(fn (string $name): string => "«{$name}»")->join(', ', ' y ').', previa de esta asignatura.'];
            }
        }
        if ($tutoring !== null && $tutoring->inscripciones()->where('fk_id_usuario', $student->getKey())->where('estado', true)->exists()) {
            return ['status' => 'enrolled', 'message' => null];
        }

        return ['status' => 'enroll', 'message' => null];
    }

    /** @param  array{name: string, email: string, phone: string|null, status: string}  $row */
    private function register(array $row, Carrera $career, AsignaturaTutoria $tutoring): void
    {
        if ($row['status'] === 'new') {
            [$student, $password] = $this->accounts->handle([
                'identification' => null,
                'name' => mb_substr($row['name'], 0, 150),
                'email' => $row['email'],
                'phone' => $row['phone'],
                'role' => 'estudiante',
                'career_id' => $career->getKey(),
            ]);
            $this->cycles->assign($student, $tutoring->ciclo);
            DB::afterCommit(fn () => $student->notify(new QueuedProvisionalPasswordNotification($password)));
        } else {
            $student = Usuario::query()->where('correo', $row['email'])->lockForUpdate()->firstOrFail();
            if ($student->fk_ciclo === null) {
                $this->cycles->assign($student, $tutoring->ciclo);
            }
        }

        if ($tutoring->fk_paralelo && ! $student->paralelos()->whereKey($tutoring->fk_paralelo)->wherePivot('estado', true)->exists()) {
            $student->paralelos()->syncWithoutDetaching([$tutoring->fk_paralelo => ['fecha_asignacion' => today(), 'estado' => true]]);
        }
        $enrollment = InscripcionTutoria::query()->firstOrNew(['fk_asig_tutoria' => $tutoring->getKey(), 'fk_id_usuario' => $student->getKey()]);
        if (! $enrollment->exists || ! $enrollment->estado) {
            $enrollment->fill(['fecha_inscripcion' => $enrollment->fecha_inscripcion ?? today(), 'estado' => true])->save();
        }
    }

    private function findSubject(int $careerId, string $name): ?Subject
    {
        /** @var Subject|null */
        return $this->findByName(Subject::query()->where('career_id', $careerId)->where('is_active', true)->get(), 'name', $name);
    }

    /** Docente por nombre, sin importar el orden de las palabras ni las tildes; primero los de la carrera. */
    private function findTeacher(int $careerId, ?string $name): ?Usuario
    {
        if ($name === null) {
            return null;
        }
        $key = self::wordKey($name);
        $candidates = Usuario::query()->where('estado', true)
            ->whereHas('roles', fn (Builder $query) => $query->where('slug', 'docente'))
            ->withExists(['teachingCareers as teaches_career' => fn (Builder $query) => $query->where('carrera.id_carrera', $careerId)])
            ->get()
            ->filter(fn (Usuario $teacher): bool => self::wordKey($teacher->nombre) === $key);

        return $candidates->sortByDesc('teaches_career')->first();
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  iterable<TModel>  $records
     * @return TModel|null
     */
    private function findByName(iterable $records, string $attribute, string $name): mixed
    {
        $needle = CatalogLookup::normalize($name);
        foreach ($records as $record) {
            if (CatalogLookup::normalize((string) $record->{$attribute}) === $needle) {
                return $record;
            }
        }

        return null;
    }

    private static function wordKey(string $name): string
    {
        $words = explode(' ', CatalogLookup::normalize($name));
        sort($words);

        return implode(' ', $words);
    }
}

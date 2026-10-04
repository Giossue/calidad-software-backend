<?php

namespace App\Http\Controllers\Api\V1\Tutoring;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Teacher\EnrollmentResource;
use App\Http\Resources\Api\V1\TutoringResource;
use App\Models\AsignaturaTutoria;
use App\Models\InscripcionTutoria;
use App\Models\Role;
use App\Models\Usuario;
use App\Notifications\ProvisionalPasswordNotification;
use App\Rules\CedulaEcuatoriana;
use App\Support\TutoringCoordinatorAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CoordinatorStudentController extends Controller
{
    private const ENROLLMENT_RELATIONS = ['estudiante.roles', 'notas', 'knowledgeMetric'];

    private const TUTORING_RELATIONS = ['ciclo', 'periodo', 'modalidad', 'paralelo', 'docente', 'horarios', 'subject'];

    public function index(Request $request, TutoringCoordinatorAccess $access): JsonResponse
    {
        Gate::authorize('viewAny', AsignaturaTutoria::class);

        $careerIds = $request->user()->coordinatedCareers()->pluck('carrera.id_carrera');

        $query = Usuario::query()
            ->where('estado', true)
            ->whereHas('roles', fn (Builder $q) => $q->where('slug', 'estudiante'))
            ->where(function (Builder $q) use ($careerIds) {
                $q->whereIn('fk_carrera', $careerIds)
                    ->orWhereNull('fk_carrera')
                    ->orWhereHas('inscripciones', fn (Builder $q2) => $q2
                        ->where('estado', true)
                        ->whereHas('asignaturaTutoria.ciclo', fn (Builder $q3) => $q3->whereIn('fk_carrera', $careerIds))
                    )
                    ->orWhereHas('paralelos.ciclos', fn (Builder $q2) => $q2->whereIn('fk_carrera', $careerIds));
            })
            ->with(['inscripciones' => fn ($q) => $q
                ->where('estado', true)
                ->whereHas('asignaturaTutoria.ciclo', fn (Builder $q2) => $q2->whereIn('fk_carrera', $careerIds))
                ->with(['asignaturaTutoria' => fn ($q2) => $q2->with(self::TUTORING_RELATIONS)]),
            ]);

        if ($search = trim((string) $request->string('search'))) {
            $like = "%{$search}%";
            $query->where(fn (Builder $q) => $q->whereLike('nombre', $like)->orWhereLike('cedula', $like)->orWhereLike('correo', $like));
        }

        $students = $query->orderBy('nombre')->paginate($request->integer('per_page', 15));

        return response()->json([
            'data' => $students->items() === [] ? [] : collect($students->items())->map(fn (Usuario $student) => $this->formatStudent($student)),
            'meta' => [
                'current_page' => $students->currentPage(),
                'last_page' => $students->lastPage(),
                'per_page' => $students->perPage(),
                'total' => $students->total(),
            ],
        ]);
    }

    public function store(Request $request, TutoringCoordinatorAccess $access): JsonResponse
    {
        Gate::authorize('viewAny', AsignaturaTutoria::class);

        $careerIds = $request->user()->coordinatedCareers()->pluck('carrera.id_carrera');

        $data = $request->validate([
            'identification' => ['required', 'digits:10', new CedulaEcuatoriana, Rule::unique('usuario', 'cedula')],
            'name' => ['required', 'string', 'max:150', 'regex:/^[\pL\s]+$/u'],
            'email' => ['required', 'email:rfc', 'max:150', 'ends_with:@ueb.edu.ec', Rule::unique('usuario', 'correo')],
            'phone' => ['nullable', 'digits:10'],
            'tutoring_id' => ['nullable', 'integer', Rule::exists('asignatura_tutoria', 'id_asig_tutoria')],
        ]);

        $password = Str::password(20, true, true, true, false);
        $student = DB::transaction(function () use ($data, $password, $careerIds): Usuario {
            $student = Usuario::query()->create([
                'cedula' => $data['identification'],
                'nombre' => $data['name'],
                'correo' => $data['email'],
                'telefono' => $data['phone'] ?? null,
                'password_hash' => $password,
                'fk_carrera' => $careerIds->first(),
                'estado' => true,
                'email_verified_at' => now(),
            ]);
            $student->roles()->attach(Role::query()->where('slug', 'estudiante')->valueOrFail('id'));

            if (! empty($data['tutoring_id'])) {
                $tutoring = AsignaturaTutoria::query()->findOrFail((int) $data['tutoring_id']);
                if ($tutoring->fk_paralelo && ! $student->paralelos()->whereKey($tutoring->fk_paralelo)->wherePivot('estado', true)->exists()) {
                    $student->paralelos()->syncWithoutDetaching([$tutoring->fk_paralelo => ['fecha_asignacion' => today(), 'estado' => true]]);
                }
                InscripcionTutoria::query()->create([
                    'fk_asig_tutoria' => $tutoring->getKey(),
                    'fk_id_usuario' => $student->getKey(),
                    'fecha_inscripcion' => today(),
                    'estado' => true,
                ]);
            }

            DB::afterCommit(fn () => $student->notify(new ProvisionalPasswordNotification($password)));

            return $student;
        });

        $student->load(['inscripciones' => fn ($q) => $q
            ->where('estado', true)
            ->whereHas('asignaturaTutoria.ciclo', fn (Builder $q2) => $q2->whereIn('fk_carrera', $careerIds))
            ->with(['asignaturaTutoria' => fn ($q2) => $q2->with(self::TUTORING_RELATIONS)]),
        ]);

        return response()->json(['data' => $this->formatStudent($student)], 201);
    }

    public function availableTutorings(Request $request, Usuario $student, TutoringCoordinatorAccess $access): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', AsignaturaTutoria::class);

        $careerIds = $request->user()->coordinatedCareers()->pluck('carrera.id_carrera');
        $enrolledTutoringIds = $student->inscripciones()->where('estado', true)->pluck('fk_asig_tutoria');

        $query = AsignaturaTutoria::query()
            ->with(self::TUTORING_RELATIONS)
            ->where('estado', true)
            ->whereHas('ciclo', fn (Builder $q) => $q->whereIn('fk_carrera', $careerIds))
            ->whereNotIn('id_asig_tutoria', $enrolledTutoringIds);

        if ($search = trim((string) $request->string('search'))) {
            $query->whereLike('nombre', "%{$search}%");
        }

        return TutoringResource::collection($query->orderByDesc('id_asig_tutoria')->limit(50)->get());
    }

    public function enroll(Request $request, Usuario $student): JsonResponse
    {
        Gate::authorize('viewAny', AsignaturaTutoria::class);
        abort_unless($student->hasRole('estudiante'), 403);

        $data = $request->validate([
            'tutoring_id' => ['required', 'integer', Rule::exists('asignatura_tutoria', 'id_asig_tutoria')],
        ]);

        $tutoring = AsignaturaTutoria::query()->with(self::TUTORING_RELATIONS)->findOrFail((int) $data['tutoring_id']);
        Gate::authorize('view', $tutoring);

        $enrollment = DB::transaction(function () use ($student, $tutoring): InscripcionTutoria {
            $locked = AsignaturaTutoria::query()->whereKey($tutoring->getKey())->lockForUpdate()->firstOrFail();

            // Assign student to the parallel if not already assigned
            if ($locked->fk_paralelo && ! $student->paralelos()->whereKey($locked->fk_paralelo)->wherePivot('estado', true)->exists()) {
                $student->paralelos()->syncWithoutDetaching([$locked->fk_paralelo => ['fecha_asignacion' => today(), 'estado' => true]]);
            }

            $enrollment = InscripcionTutoria::query()->firstOrNew(['fk_asig_tutoria' => $locked->getKey(), 'fk_id_usuario' => $student->getKey()]);
            if ($enrollment->exists && $enrollment->estado) {
                throw ValidationException::withMessages(['tutoring_id' => 'El estudiante ya está inscrito en esta tutoría.']);
            }
            $enrollment->fill(['fecha_inscripcion' => $enrollment->fecha_inscripcion ?? today(), 'estado' => true])->save();

            return $enrollment->load(self::ENROLLMENT_RELATIONS);
        });

        return response()->json(['data' => EnrollmentResource::make($enrollment)], 201);
    }

    public function unenroll(Request $request, Usuario $student, InscripcionTutoria $enrollment): JsonResponse
    {
        Gate::authorize('viewAny', AsignaturaTutoria::class);
        abort_unless($enrollment->fk_id_usuario === $student->getKey(), 404);

        $enrollment->update(['estado' => false]);

        return response()->json(['data' => EnrollmentResource::make($enrollment->refresh()->load(self::ENROLLMENT_RELATIONS))]);
    }

    /** @return array<string, mixed> */
    private function formatStudent(Usuario $student): array
    {
        $enrollments = $student->relationLoaded('inscripciones') ? $student->inscripciones : collect();

        return [
            'id' => $student->getKey(),
            'identification' => $student->cedula,
            'name' => $student->nombre,
            'email' => $student->correo,
            'phone' => $student->telefono,
            'is_active' => $student->estado,
            'tutoring_count' => $enrollments->count(),
            'tutorings' => $enrollments->map(fn (InscripcionTutoria $enrollment) => [
                'enrollment_id' => $enrollment->getKey(),
                'tutoring_id' => $enrollment->fk_asig_tutoria,
                'subject_name' => $enrollment->asignaturaTutoria->nombre ?? '—',
                'cycle_name' => $enrollment->asignaturaTutoria->ciclo->nombre ?? '—',
                'section_name' => $enrollment->asignaturaTutoria->paralelo->nombre ?? null,
                'period_name' => $enrollment->asignaturaTutoria->periodo->nombre ?? '—',
                'is_active' => $enrollment->estado,
            ])->values(),
        ];
    }
}

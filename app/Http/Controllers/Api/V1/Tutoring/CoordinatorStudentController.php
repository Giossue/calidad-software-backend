<?php

namespace App\Http\Controllers\Api\V1\Tutoring;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Teacher\EnrollmentResource;
use App\Http\Resources\Api\V1\TutoringResource;
use App\Models\AsignaturaTutoria;
use App\Models\Ciclo;
use App\Models\InscripcionTutoria;
use App\Models\Malla;
use App\Models\Role;
use App\Models\Subject;
use App\Models\Usuario;
use App\Notifications\ProvisionalPasswordNotification;
use App\Rules\CedulaOPasaporte;
use App\Rules\PersonNameCount;
use App\Support\EnrollmentNumber;
use App\Support\InstitutionalEmail;
use App\Support\Mallas;
use App\Support\PendingSubjects;
use App\Support\PersonName;
use App\Support\ProvisionalPassword;
use App\Support\StudentCycle;
use App\Support\TutoringCoordinatorAccess;
use App\Support\TutoringPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CoordinatorStudentController extends Controller
{
    private const ENROLLMENT_RELATIONS = ['estudiante.roles', 'notas', 'knowledgeMetric'];

    private const TUTORING_RELATIONS = ['ciclo', 'periodo', 'modalidad', 'paralelo', 'docente', 'horarios', 'subject.malla'];

    public function index(Request $request, TutoringCoordinatorAccess $access): JsonResponse
    {
        Gate::authorize('viewAny', AsignaturaTutoria::class);

        $careerIds = $request->user()->coordinatedCareers()->pluck('carrera.id_carrera');
        $selectedCareerId = $request->integer('career_id');
        if ($selectedCareerId) {
            $access->authorizeCareer($request->user(), $selectedCareerId);
            $careerIds = collect([$selectedCareerId]);
        }
        $cycleNumber = $request->integer('cycle_number');

        $query = Usuario::query()
            ->where('estado', true)
            ->whereHas('roles', fn (Builder $q) => $q->where('slug', 'estudiante'))
            ->where(function (Builder $q) use ($careerIds, $selectedCareerId) {
                $q->whereIn('fk_carrera', $careerIds)
                    ->when(! $selectedCareerId, fn (Builder $q2) => $q2->orWhereNull('fk_carrera'))
                    ->orWhereHas('inscripciones', fn (Builder $q2) => $q2
                        ->where('estado', true)
                        ->whereHas('asignaturaTutoria.ciclo', fn (Builder $q3) => $q3->whereIn('fk_carrera', $careerIds))
                    )
                    ->orWhereHas('ciclo', fn (Builder $q2) => $q2->whereIn('fk_carrera', $careerIds));
            })
            ->with(['malla', 'ciclo.paralelo', 'periodRecords.periodo', 'periodRecords.ciclo', 'pendingSubjects:subjects.id,subjects.code,subjects.name', 'inscripciones' => fn ($q) => $q
                ->where('estado', true)
                ->whereHas('asignaturaTutoria.ciclo', fn (Builder $q2) => $q2->whereIn('fk_carrera', $careerIds))
                ->with(['asignaturaTutoria' => fn ($q2) => $q2->with(self::TUTORING_RELATIONS)]),
            ]);

        // El coordinador trabaja con una malla a la vez: la que sigue cada estudiante.
        if ($mallaId = $request->integer('malla_id')) {
            $query->where('malla_id', $mallaId);
        }

        if ($search = trim((string) $request->string('search'))) {
            $like = "%{$search}%";
            $query->where(fn (Builder $q) => $q->whereLike('nombre', $like)->orWhereLike('cedula', $like)->orWhereLike('correo', $like));
        }

        // El ciclo del estudiante es el registrado en su cuenta; el paralelo no lo identifica
        // porque es compartido por todos los ciclos.
        if ($cycleNumber) {
            $query->whereHas('ciclo', fn (Builder $q) => $q->whereIn('fk_carrera', $careerIds)->where('numero', $cycleNumber));
        } elseif ($request->boolean('without_cycle')) {
            $query->whereNull('fk_ciclo');
        }

        $enrollmentScope = fn (Builder $q) => $q->where('estado', true)
            ->whereHas('asignaturaTutoria.ciclo', fn (Builder $q2) => $q2->whereIn('fk_carrera', $careerIds));
        $tutoringStatus = $request->string('tutoring_status')->toString();
        if ($tutoringStatus === 'with') {
            $query->whereHas('inscripciones', $enrollmentScope);
        } elseif ($tutoringStatus === 'without') {
            $query->whereDoesntHave('inscripciones', $enrollmentScope);
        }

        $students = $query->orderBy('nombre')->paginate($request->integer('per_page', 15));

        return response()->json([
            'data' => $students->items() === [] ? [] : collect($students->items())->map(fn (Usuario $student) => $this->formatStudent($student)),
            'meta' => [
                'current_page' => $students->currentPage(),
                'last_page' => $students->lastPage(),
                'per_page' => $students->perPage(),
                'from' => $students->firstItem(),
                'to' => $students->lastItem(),
                'total' => $students->total(),
            ],
        ]);
    }

    public function store(Request $request, TutoringCoordinatorAccess $access, StudentCycle $cycles): JsonResponse
    {
        Gate::authorize('viewAny', AsignaturaTutoria::class);

        $careerIds = $request->user()->coordinatedCareers()->pluck('carrera.id_carrera');
        if ($selectedCareerId = $request->integer('career_id')) {
            $access->authorizeCareer($request->user(), $selectedCareerId);
            $careerIds = collect([$selectedCareerId]);
        }

        // Nombres y apellidos llegan por separado y se guardan juntos como "apellidos nombres".
        foreach (['first_names', 'last_names'] as $field) {
            if (is_string($request->input($field))) {
                $request->merge([$field => PersonName::normalize($request->input($field))]);
            }
        }
        $data = PersonName::combine($request->validate([
            'identification' => ['required', 'string', new CedulaOPasaporte, Rule::unique('usuario', 'cedula')],
            ...PersonNameCount::rules(),
            'email' => ['required', 'email:rfc', 'max:150', InstitutionalEmail::rule(), Rule::unique('usuario', 'correo')],
            'phone' => ['nullable', 'digits:10'],
            'cycle_id' => ['required', 'integer', Rule::exists('ciclo', 'id_ciclo')->where('estado', true)],
            'tutoring_id' => ['nullable', 'integer', Rule::exists('asignatura_tutoria', 'id_asig_tutoria')],
            'malla_id' => ['nullable', 'integer', Rule::exists('malla', 'id')],
            // Matrícula de la inscripción en la tutoría inicial, si se indica una.
            'enrollment_number' => EnrollmentNumber::rules(),
        ], [...PersonNameCount::messages(), ...EnrollmentNumber::messages()], ['first_names' => 'nombres', 'last_names' => 'apellidos', 'cycle_id' => 'ciclo', 'tutoring_id' => 'tutoría', 'malla_id' => 'malla', ...EnrollmentNumber::attributes()]));

        $cycle = Ciclo::query()->findOrFail((int) $data['cycle_id']);
        $access->authorizeCareer($request->user(), $cycle->fk_carrera);
        if (! empty($data['tutoring_id'])) {
            $belongs = AsignaturaTutoria::query()->whereKey((int) $data['tutoring_id'])->where('fk_ciclo', $cycle->getKey())->where('estado', true)->exists();
            if (! $belongs || $cycles->isDegreeCycle($cycle)) {
                throw ValidationException::withMessages(['tutoring_id' => 'Selecciona una tutoría activa del ciclo del estudiante.']);
            }
            TutoringPeriod::assertCurrent(AsignaturaTutoria::query()->findOrFail((int) $data['tutoring_id']), 'tutoring_id');
        }
        // La malla indicada debe ser de la carrera del ciclo. Registrado por una
        // tutoría, sigue la malla de la tutoría; si no, la indicada o la vigente.
        $requested = ! empty($data['malla_id']) ? Malla::query()->findOrFail((int) $data['malla_id']) : null;
        if ($requested !== null && $requested->career_id !== $cycle->fk_carrera) {
            throw ValidationException::withMessages(['malla_id' => 'La malla no pertenece a la carrera.']);
        }
        $malla = ! empty($data['tutoring_id'])
            ? Mallas::ofTutoring(AsignaturaTutoria::query()->findOrFail((int) $data['tutoring_id']))
            : $requested;

        $password = ProvisionalPassword::generate();
        $student = DB::transaction(function () use ($data, $password, $cycle, $cycles, $malla): Usuario {
            $student = Usuario::query()->create([
                'cedula' => $data['identification'],
                'nombre' => $data['name'],
                'correo' => $data['email'],
                'telefono' => $data['phone'] ?? null,
                'password_hash' => $password,
                'fk_carrera' => $cycle->fk_carrera,
                'estado' => true,
                'email_verified_at' => now(),
                'must_complete_profile' => true,
            ]);
            $student->roles()->attach(Role::query()->where('slug', 'estudiante')->valueOrFail('id'));
            $cycles->assign($student, $cycle, $malla);

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
                    'numero_matricula' => $data['enrollment_number'] ?? EnrollmentNumber::FIRST,
                ]);
            }

            DB::afterCommit(fn () => $student->notify(new ProvisionalPasswordNotification($password)));

            return $student;
        });

        $student->load(['malla', 'ciclo.paralelo', 'periodRecords.periodo', 'periodRecords.ciclo', 'pendingSubjects:subjects.id,subjects.code,subjects.name', 'inscripciones' => fn ($q) => $q
            ->where('estado', true)
            ->whereHas('asignaturaTutoria.ciclo', fn (Builder $q2) => $q2->whereIn('fk_carrera', $careerIds))
            ->with(['asignaturaTutoria' => fn ($q2) => $q2->with(self::TUTORING_RELATIONS)]),
        ]);

        return response()->json(['data' => $this->formatStudent($student)], 201);
    }

    public function availableTutorings(Request $request, Usuario $student, TutoringCoordinatorAccess $access): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', AsignaturaTutoria::class);
        // Desde el ciclo de titulación el estudiante ya no recibe tutorías.
        if (app(StudentCycle::class)->isDegreeStudent($student)) {
            return TutoringResource::collection(collect());
        }

        $careerIds = $request->user()->coordinatedCareers()->pluck('carrera.id_carrera');
        $enrolledTutoringIds = $student->inscripciones()->where('estado', true)->pluck('fk_asig_tutoria');

        $query = AsignaturaTutoria::query()
            ->with(self::TUTORING_RELATIONS)
            ->where('estado', true)
            ->whereHas('ciclo', fn (Builder $q) => $q->whereIn('fk_carrera', $careerIds))
            ->whereNotIn('id_asig_tutoria', $enrolledTutoringIds);
        // Solo las de la malla que sigue el estudiante.
        if ($student->malla_id !== null) {
            $query->where(fn (Builder $q) => $q->whereNull('subject_id')->orWhereHas('subject', fn (Builder $s) => $s->where('malla_id', $student->malla_id)));
        }
        // Las que continúan a una asignatura que arrastra no se ofrecen hasta que la apruebe.
        $blocked = PendingSubjects::blockedSubjectIds($student);
        if ($blocked->isNotEmpty()) {
            $query->where(fn (Builder $q) => $q->whereNull('subject_id')->orWhereNotIn('subject_id', $blocked));
        }

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
            'enrollment_number' => EnrollmentNumber::rules(),
        ], EnrollmentNumber::messages(), EnrollmentNumber::attributes());
        app(StudentCycle::class)->assertCanTakeTutorings($student, 'tutoring_id');

        $tutoring = AsignaturaTutoria::query()->with(self::TUTORING_RELATIONS)->findOrFail((int) $data['tutoring_id']);
        Gate::authorize('view', $tutoring);
        TutoringPeriod::assertCurrent($tutoring);
        Mallas::assertFollows($student, $tutoring, 'tutoring_id');
        PendingSubjects::assertCanTake($student, $tutoring, 'tutoring_id');
        app(StudentCycle::class)->assertSingleSectionPerSubject($student, $tutoring, 'tutoring_id');

        $enrollment = DB::transaction(function () use ($student, $tutoring, $data): InscripcionTutoria {
            $locked = AsignaturaTutoria::query()->whereKey($tutoring->getKey())->lockForUpdate()->firstOrFail();

            // Assign student to the parallel if not already assigned
            if ($locked->fk_paralelo && ! $student->paralelos()->whereKey($locked->fk_paralelo)->wherePivot('estado', true)->exists()) {
                $student->paralelos()->syncWithoutDetaching([$locked->fk_paralelo => ['fecha_asignacion' => today(), 'estado' => true]]);
            }
            // Un estudiante sin ciclo registrado pasa a cursar el ciclo de su primera tutoría.
            if ($student->fk_ciclo === null && $locked->ciclo !== null) {
                app(StudentCycle::class)->assign($student, $locked->ciclo, Mallas::ofTutoring($locked));
            }

            $enrollment = InscripcionTutoria::query()->firstOrNew(['fk_asig_tutoria' => $locked->getKey(), 'fk_id_usuario' => $student->getKey()]);
            if ($enrollment->exists && $enrollment->estado) {
                throw ValidationException::withMessages(['tutoring_id' => 'El estudiante ya está inscrito en esta tutoría.']);
            }
            $enrollment->fill([
                'fecha_inscripcion' => $enrollment->fecha_inscripcion ?? today(), 'estado' => true,
                'numero_matricula' => $data['enrollment_number'] ?? EnrollmentNumber::FIRST,
            ])->save();

            return $enrollment->load(self::ENROLLMENT_RELATIONS);
        });

        return response()->json(['data' => EnrollmentResource::make($enrollment)], 201);
    }

    public function unenroll(Request $request, Usuario $student, InscripcionTutoria $enrollment): JsonResponse
    {
        Gate::authorize('viewAny', AsignaturaTutoria::class);
        abort_unless($enrollment->fk_id_usuario === $student->getKey(), 404);
        TutoringPeriod::assertCurrent($enrollment->asignaturaTutoria);

        $enrollment->update(['estado' => false]);

        return response()->json(['data' => EnrollmentResource::make($enrollment->refresh()->load(self::ENROLLMENT_RELATIONS))]);
    }

    /**
     * Asigna el primer ciclo a un estudiante que aún no tiene uno. Después, el
     * ciclo cambia solo con sus inscripciones en tutorías del período.
     */
    public function updateCycle(Request $request, Usuario $student, TutoringCoordinatorAccess $access, StudentCycle $cycles): JsonResponse
    {
        Gate::authorize('viewAny', AsignaturaTutoria::class);
        abort_unless($student->hasRole('estudiante'), 403);
        if ($student->fk_carrera !== null) {
            $access->authorizeCareer($request->user(), $student->fk_carrera);
        }

        $data = $request->validate([
            'cycle_id' => ['required', 'integer', Rule::exists('ciclo', 'id_ciclo')->where('estado', true)],
        ], [], ['cycle_id' => 'ciclo']);
        $cycle = Ciclo::query()->findOrFail((int) $data['cycle_id']);
        $access->authorizeCareer($request->user(), $cycle->fk_carrera);
        if ($student->fk_ciclo !== null) {
            throw ValidationException::withMessages(['cycle_id' => 'El ciclo del estudiante se actualiza solo con sus tutorías del período.']);
        }

        $cycles->assign($student, $cycle);
        $student->load(['malla', 'ciclo.paralelo', 'periodRecords.periodo', 'periodRecords.ciclo', 'pendingSubjects:subjects.id,subjects.code,subjects.name', 'inscripciones' => fn ($q) => $q
            ->where('estado', true)
            ->whereHas('asignaturaTutoria.ciclo', fn (Builder $q2) => $q2->where('fk_carrera', $cycle->fk_carrera))
            ->with(['asignaturaTutoria' => fn ($q2) => $q2->with(self::TUTORING_RELATIONS)]),
        ]);

        return response()->json(['data' => $this->formatStudent($student)]);
    }

    /**
     * Mueve al estudiante a otra malla de su carrera. Antes debe retirarse de sus
     * tutorías activas de otra malla en el período vigente: las mallas son
     * independientes y lo aprobado en una no cuenta en la otra.
     */
    public function updateMalla(Request $request, Usuario $student, TutoringCoordinatorAccess $access): JsonResponse
    {
        Gate::authorize('viewAny', AsignaturaTutoria::class);
        abort_unless($student->hasRole('estudiante') && $student->fk_carrera !== null, 403);
        $access->authorizeCareer($request->user(), $student->fk_carrera);

        $data = $request->validate([
            'malla_id' => ['required', 'integer', Rule::exists('malla', 'id')],
        ], [], ['malla_id' => 'malla']);
        $malla = Malla::query()->findOrFail((int) $data['malla_id']);
        if ($malla->career_id !== $student->fk_carrera) {
            throw ValidationException::withMessages(['malla_id' => 'La malla debe ser de la carrera del estudiante.']);
        }
        $activeElsewhere = $student->inscripciones()->where('estado', true)
            ->whereHas('asignaturaTutoria', fn (Builder $q) => $q
                ->whereHas('periodo', fn (Builder $period) => $period->where('estado', true))
                ->whereHas('subject', fn (Builder $subject) => $subject->where('malla_id', '!=', $malla->getKey())))
            ->exists();
        if ($activeElsewhere) {
            throw ValidationException::withMessages(['malla_id' => 'Retira primero sus tutorías activas de la otra malla.']);
        }

        Mallas::assign($student, $malla);
        $student->load(['malla', 'ciclo.paralelo', 'periodRecords.periodo', 'periodRecords.ciclo', 'pendingSubjects:subjects.id,subjects.code,subjects.name', 'inscripciones' => fn ($q) => $q
            ->where('estado', true)
            ->whereHas('asignaturaTutoria.ciclo', fn (Builder $q2) => $q2->where('fk_carrera', $student->fk_carrera))
            ->with(['asignaturaTutoria' => fn ($q2) => $q2->with(self::TUTORING_RELATIONS)]),
        ]);

        return response()->json(['data' => $this->formatStudent($student)]);
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
            'malla_id' => $student->malla_id,
            'malla_name' => $student->malla?->name,
            ...app(StudentCycle::class)->describe($student),
            'cycle_history' => app(StudentCycle::class)->history($student),
            'pending_subjects' => $student->relationLoaded('pendingSubjects')
                ? $student->pendingSubjects->map(fn (Subject $subject) => ['id' => $subject->id, 'code' => $subject->code, 'name' => $subject->name])->values()
                : [],
            'tutoring_count' => $enrollments->count(),
            'tutorings' => $enrollments->map(fn (InscripcionTutoria $enrollment) => [
                'enrollment_id' => $enrollment->getKey(),
                'tutoring_id' => $enrollment->fk_asig_tutoria,
                'subject_name' => $enrollment->asignaturaTutoria->nombre ?? '—',
                'cycle_name' => $enrollment->asignaturaTutoria->ciclo->nombre ?? '—',
                'section_name' => $enrollment->asignaturaTutoria->paralelo->nombre ?? null,
                'period_name' => $enrollment->asignaturaTutoria->periodo->nombre ?? '—',
                'is_active' => $enrollment->estado,
                'enrollment_number' => $enrollment->numero_matricula,
                'attendance_required' => $enrollment->requiresAttendance(),
            ])->values(),
        ];
    }
}

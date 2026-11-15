<?php

namespace App\Http\Controllers\Api\V1\Tutoring;

use App\Actions\Teacher\ManageEnrollment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Teacher\AbandonmentRequest;
use App\Http\Resources\Api\V1\Teacher\AvailableStudentResource;
use App\Http\Resources\Api\V1\Teacher\EnrollmentResource;
use App\Models\AsignaturaTutoria;
use App\Models\InscripcionTutoria;
use App\Models\Role;
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
use App\Support\TutoringPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CoordinatorEnrollmentController extends Controller
{
    private const PERSON_NAME_ATTRIBUTES = ['first_names' => 'nombres', 'last_names' => 'apellidos'];

    public function index(Request $request, AsignaturaTutoria $tutoring): AnonymousResourceCollection
    {
        Gate::authorize('view', $tutoring);

        $query = $tutoring->inscripciones()->with(ManageEnrollment::RELATIONS);
        if ($request->filled('search')) {
            $search = '%'.$request->string('search').'%';
            $query->whereHas('estudiante', fn (Builder $query) => $query->whereLike('nombre', $search)->orWhereLike('cedula', $search)->orWhereLike('correo', $search));
        }
        if ($request->filled('status')) {
            $query->where('estado', $request->input('status') === 'active');
        }

        return EnrollmentResource::collection($query->orderByDesc('estado')
            ->orderBy(Usuario::query()->select('nombre')->whereColumn('usuario.id_usuario', 'inscripcion_tutoria.fk_id_usuario'))
            ->orderBy('id_inscripcion')->paginate($request->integer('per_page', 15)));
    }

    public function available(Request $request, AsignaturaTutoria $tutoring): AnonymousResourceCollection
    {
        Gate::authorize('view', $tutoring);

        $query = Usuario::query()->where('estado', true)->whereHas('roles', fn (Builder $query) => $query->where('slug', 'estudiante'))
            ->whereHas('paralelos', fn (Builder $query) => $query->where('paralelo.id_paralelo', $tutoring->fk_paralelo)->where('usuario_paralelo.estado', true))
            ->whereDoesntHave('inscripciones', fn (Builder $query) => $query->where('fk_asig_tutoria', $tutoring->getKey())->where('estado', true));
        StudentCycle::scopeTutoringStage($query);
        PendingSubjects::scopeCanTake($query, $tutoring);
        Mallas::scopeFollowing($query, $tutoring);
        if ($request->filled('search')) {
            $search = '%'.$request->string('search').'%';
            $query->where(fn (Builder $query) => $query->whereLike('nombre', $search)->orWhereLike('cedula', $search)->orWhereLike('correo', $search));
        }

        return AvailableStudentResource::collection($query->orderBy('nombre')->orderBy('id_usuario')->limit(100)->get());
    }

    public function store(Request $request, AsignaturaTutoria $tutoring): JsonResponse
    {
        Gate::authorize('view', $tutoring);

        $data = $this->validateEnrollment($request);

        $enrollment = DB::transaction(function () use ($tutoring, $data): InscripcionTutoria {
            $locked = AsignaturaTutoria::query()->whereKey($tutoring->getKey())->lockForUpdate()->firstOrFail();
            Gate::authorize('view', $locked);
            TutoringPeriod::assertCurrent($locked);

            if (isset($data['student_id'])) {
                $student = Usuario::query()->whereKey($data['student_id'])->lockForUpdate()->firstOrFail();
                if (! $student->estado || ! $student->hasRole('estudiante') || ! $student->paralelos()->whereKey($locked->fk_paralelo)->wherePivot('estado', true)->exists()) {
                    throw ValidationException::withMessages(['student_id' => 'Selecciona un estudiante activo del paralelo de esta tutoría.']);
                }
                app(StudentCycle::class)->assertCanTakeTutorings($student);
                Mallas::assertFollows($student, $locked);
                PendingSubjects::assertCanTake($student, $locked);
                app(StudentCycle::class)->assertSingleSectionPerSubject($student, $locked);
            } else {
                $password = ProvisionalPassword::generate();
                $student = Usuario::query()->create([
                    'cedula' => $data['identification'], 'nombre' => $data['name'],
                    'correo' => $data['email'], 'telefono' => $data['phone'] ?? null,
                    'password_hash' => $password, 'estado' => true, 'email_verified_at' => now(),
                    'must_complete_profile' => true,
                ]);
                $student->roles()->attach(Role::query()->where('slug', 'estudiante')->valueOrFail('id'));
                $student->paralelos()->attach($locked->fk_paralelo, ['fecha_asignacion' => today(), 'estado' => true]);
                DB::afterCommit(fn () => $student->notify(new ProvisionalPasswordNotification($password)));
            }
            // Un estudiante sin ciclo registrado pasa a cursar el ciclo de la tutoría;
            // uno nuevo sigue la malla de la tutoría.
            if ($student->fk_ciclo === null && $locked->ciclo !== null) {
                app(StudentCycle::class)->assign($student, $locked->ciclo, Mallas::ofTutoring($locked));
            }

            $enrollment = InscripcionTutoria::query()->firstOrNew(['fk_asig_tutoria' => $locked->getKey(), 'fk_id_usuario' => $student->getKey()]);
            if ($enrollment->exists && $enrollment->estado) {
                throw ValidationException::withMessages(['student_id' => 'El estudiante ya está inscrito en esta tutoría.']);
            }
            $enrollment->fill([
                'fecha_inscripcion' => $enrollment->fecha_inscripcion ?? today(), 'estado' => true,
                'numero_matricula' => $data['enrollment_number'] ?? EnrollmentNumber::FIRST,
            ])->save();

            return $enrollment->load(ManageEnrollment::RELATIONS);
        });

        return EnrollmentResource::make($enrollment)->response()->setStatusCode(201);
    }

    public function update(Request $request, AsignaturaTutoria $tutoring, InscripcionTutoria $enrollment): EnrollmentResource
    {
        Gate::authorize('view', $tutoring);
        abort_unless($enrollment->fk_asig_tutoria === $tutoring->getKey(), 404);

        // Los nombres y apellidos son opcionales pero llegan juntos; sin ellos se conserva el nombre.
        $this->normalizePersonNames($request);
        $data = PersonName::combine($request->validate([
            ...PersonNameCount::rules(false),
            'phone' => ['nullable', 'digits:10'],
            'enrollment_number' => EnrollmentNumber::rules(),
        ], [...PersonNameCount::messages(), ...EnrollmentNumber::messages()], [...self::PERSON_NAME_ATTRIBUTES, ...EnrollmentNumber::attributes()]));

        ManageEnrollment::applyEdit($enrollment, $data);

        return EnrollmentResource::make($enrollment->refresh()->load(ManageEnrollment::RELATIONS));
    }

    public function deactivate(Request $request, AsignaturaTutoria $tutoring, InscripcionTutoria $enrollment): EnrollmentResource
    {
        Gate::authorize('view', $tutoring);
        abort_unless($enrollment->fk_asig_tutoria === $tutoring->getKey(), 404);
        TutoringPeriod::assertCurrent($tutoring);

        $enrollment->update(['estado' => false]);

        return EnrollmentResource::make($enrollment->refresh()->load(ManageEnrollment::RELATIONS));
    }

    public function abandonment(Request $request, AsignaturaTutoria $tutoring, InscripcionTutoria $enrollment, ManageEnrollment $action): EnrollmentResource
    {
        Gate::authorize('view', $tutoring);
        abort_unless($enrollment->fk_asig_tutoria === $tutoring->getKey(), 404);
        $data = $request->validate(AbandonmentRequest::fieldRules(), [], AbandonmentRequest::fieldAttributes());
        TutoringPeriod::assertCurrent($tutoring);

        return EnrollmentResource::make($action->setAbandonment($enrollment, (bool) $data['abandoned'], $data['reason'] ?? null));
    }

    public function reenroll(Request $request, AsignaturaTutoria $tutoring, InscripcionTutoria $enrollment): EnrollmentResource
    {
        Gate::authorize('view', $tutoring);
        abort_unless($enrollment->fk_asig_tutoria === $tutoring->getKey(), 404);

        $student = $enrollment->estudiante;
        abort_unless($student->estado, 422);
        TutoringPeriod::assertCurrent($tutoring);
        Mallas::assertFollows($student, $tutoring);
        app(StudentCycle::class)->assertSingleSectionPerSubject($student, $tutoring);

        $enrollment->update(['estado' => true]);

        return EnrollmentResource::make($enrollment->refresh()->load(ManageEnrollment::RELATIONS));
    }

    /**
     * Un estudiante nuevo se registra con nombres y apellidos, que se guardan juntos
     * como "apellidos nombres"; uno ya registrado se inscribe solo con `student_id`.
     *
     * @return array<string, mixed>
     */
    private function validateEnrollment(Request $request): array
    {
        $existing = Rule::prohibitedIf($request->filled('student_id'));
        $this->normalizePersonNames($request);
        // Las reglas compartidas de nombres, obligatorias solo para un estudiante nuevo.
        $names = array_map(fn (array $rules): array => [$existing, 'required_without:student_id', ...array_slice($rules, 1)], PersonNameCount::rules());

        return PersonName::combine($request->validate([
            'student_id' => ['nullable', 'integer', Rule::exists('usuario', 'id_usuario')->where('estado', true)],
            'identification' => [$existing, 'required_without:student_id', 'string', new CedulaOPasaporte, Rule::unique('usuario', 'cedula')],
            ...$names,
            'email' => [$existing, 'required_without:student_id', 'email:rfc', 'max:150', InstitutionalEmail::rule(), Rule::unique('usuario', 'correo')],
            'phone' => [$existing, 'nullable', 'digits:10'],
            'enrollment_number' => EnrollmentNumber::rules(),
        ], [...PersonNameCount::messages(), ...EnrollmentNumber::messages()], [...self::PERSON_NAME_ATTRIBUTES, ...EnrollmentNumber::attributes()]));
    }

    private function normalizePersonNames(Request $request): void
    {
        foreach (['first_names', 'last_names'] as $field) {
            if (is_string($request->input($field))) {
                $request->merge([$field => PersonName::normalize($request->input($field))]);
            }
        }
    }
}

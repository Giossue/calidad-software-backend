<?php

namespace App\Http\Controllers\Api\V1\Tutoring;

use App\Actions\Teacher\ManageEnrollment;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Teacher\AvailableStudentResource;
use App\Http\Resources\Api\V1\Teacher\EnrollmentResource;
use App\Models\AsignaturaTutoria;
use App\Models\InscripcionTutoria;
use App\Models\Role;
use App\Models\Usuario;
use App\Notifications\ProvisionalPasswordNotification;
use App\Rules\CedulaOPasaporte;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CoordinatorEnrollmentController extends Controller
{
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

            if (isset($data['student_id'])) {
                $student = Usuario::query()->whereKey($data['student_id'])->lockForUpdate()->firstOrFail();
                if (! $student->estado || ! $student->hasRole('estudiante') || ! $student->paralelos()->whereKey($locked->fk_paralelo)->wherePivot('estado', true)->exists()) {
                    throw ValidationException::withMessages(['student_id' => 'Selecciona un estudiante activo del paralelo de esta tutoría.']);
                }
            } else {
                $password = Str::password(20, true, true, true, false);
                $student = Usuario::query()->create([
                    'cedula' => $data['identification'], 'nombre' => $data['name'],
                    'correo' => $data['email'], 'telefono' => $data['phone'] ?? null,
                    'password_hash' => $password, 'estado' => true, 'email_verified_at' => now(),
                ]);
                $student->roles()->attach(Role::query()->where('slug', 'estudiante')->valueOrFail('id'));
                $student->paralelos()->attach($locked->fk_paralelo, ['fecha_asignacion' => today(), 'estado' => true]);
                DB::afterCommit(fn () => $student->notify(new ProvisionalPasswordNotification($password)));
            }

            $enrollment = InscripcionTutoria::query()->firstOrNew(['fk_asig_tutoria' => $locked->getKey(), 'fk_id_usuario' => $student->getKey()]);
            if ($enrollment->exists && $enrollment->estado) {
                throw ValidationException::withMessages(['student_id' => 'El estudiante ya está inscrito en esta tutoría.']);
            }
            $enrollment->fill(['fecha_inscripcion' => $enrollment->fecha_inscripcion ?? today(), 'estado' => true])->save();

            return $enrollment->load(ManageEnrollment::RELATIONS);
        });

        return EnrollmentResource::make($enrollment)->response()->setStatusCode(201);
    }

    public function update(Request $request, AsignaturaTutoria $tutoring, InscripcionTutoria $enrollment): EnrollmentResource
    {
        Gate::authorize('view', $tutoring);
        abort_unless($enrollment->fk_asig_tutoria === $tutoring->getKey(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', 'regex:/^[\pL\s]+$/u'],
            'phone' => ['nullable', 'digits:10'],
        ]);

        $student = $enrollment->estudiante;
        abort_unless($student->roleSlugs()->count() === 1 && $student->hasRole('estudiante'), 403);
        $student->update(['nombre' => $data['name'], 'telefono' => $data['phone'] ?? null]);

        return EnrollmentResource::make($enrollment->refresh()->load(ManageEnrollment::RELATIONS));
    }

    public function deactivate(Request $request, AsignaturaTutoria $tutoring, InscripcionTutoria $enrollment): EnrollmentResource
    {
        Gate::authorize('view', $tutoring);
        abort_unless($enrollment->fk_asig_tutoria === $tutoring->getKey(), 404);

        $enrollment->update(['estado' => false]);

        return EnrollmentResource::make($enrollment->refresh()->load(ManageEnrollment::RELATIONS));
    }

    public function reenroll(Request $request, AsignaturaTutoria $tutoring, InscripcionTutoria $enrollment): EnrollmentResource
    {
        Gate::authorize('view', $tutoring);
        abort_unless($enrollment->fk_asig_tutoria === $tutoring->getKey(), 404);

        $student = $enrollment->estudiante;
        abort_unless($student->estado, 422);

        $enrollment->update(['estado' => true]);

        return EnrollmentResource::make($enrollment->refresh()->load(ManageEnrollment::RELATIONS));
    }

    /** @return array<string, mixed> */
    private function validateEnrollment(Request $request): array
    {
        $existing = Rule::prohibitedIf($request->filled('student_id'));

        return $request->validate([
            'student_id' => ['nullable', 'integer', Rule::exists('usuario', 'id_usuario')->where('estado', true)],
            'identification' => [$existing, 'required_without:student_id', 'string', new CedulaOPasaporte, Rule::unique('usuario', 'cedula')],
            'name' => [$existing, 'required_without:student_id', 'string', 'max:150', 'regex:/^[\pL\s]+$/u'],
            'email' => [$existing, 'required_without:student_id', 'email:rfc', 'max:150', 'ends_with:@ueb.edu.ec', Rule::unique('usuario', 'correo')],
            'phone' => [$existing, 'nullable', 'digits:10'],
        ]);
    }
}

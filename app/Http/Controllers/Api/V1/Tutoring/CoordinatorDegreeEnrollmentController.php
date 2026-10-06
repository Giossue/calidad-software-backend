<?php

namespace App\Http\Controllers\Api\V1\Tutoring;

use App\Http\Controllers\Controller;
use App\Models\Carrera;
use App\Models\MatriculaTitulacion;
use App\Models\PeriodoAcademico;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class CoordinatorDegreeEnrollmentController extends Controller
{
    private function authorizeCoordinator(Usuario $user): void
    {
        abort_unless(
            $user->estado && $user->hasAnyRole(['coordinador_titulacion', 'coordinador_carrera', 'administrador']),
            Response::HTTP_FORBIDDEN,
            'No tienes permisos para gestionar la matrícula de titulación.'
        );
    }

    /**
     * @return Collection<int, int>
     */
    private function getCoordinatorCareerIds(Usuario $user): Collection
    {
        if ($user->hasRole('administrador')) {
            return Carrera::pluck('id_carrera');
        }

        $careerIds = collect();
        if ($user->coordinatedCareers()->exists()) {
            $careerIds = $careerIds->merge($user->coordinatedCareers()->pluck('carrera.id_carrera'));
        }
        if ($user->fk_carrera) {
            $careerIds = $careerIds->push($user->fk_carrera);
        }

        return $careerIds->unique()->values();
    }

    public function index(Request $request): JsonResponse
    {
        /** @var Usuario $user */
        $user = $request->user();
        $this->authorizeCoordinator($user);

        $currentPeriod = PeriodoAcademico::query()->where('estado', true)->first();
        if (! $currentPeriod) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 15,
                    'total' => 0,
                    'current_period' => null,
                ],
            ]);
        }

        $careerIds = $this->getCoordinatorCareerIds($user);
        if ($selectedCareerId = $request->integer('career_id')) {
            $careerIds = $careerIds->contains($selectedCareerId) ? collect([$selectedCareerId]) : collect();
        }

        $query = Usuario::query()
            ->where('estado', true)
            ->whereHas('roles', fn (Builder $q) => $q->where('slug', 'estudiante'))
            ->where(function (Builder $q) use ($careerIds) {
                if ($careerIds->isEmpty()) {
                    $q->whereRaw('1 = 0');

                    return;
                }

                $q->whereIn('fk_carrera', $careerIds)
                    ->orWhereHas('inscripciones', fn (Builder $q2) => $q2
                        ->where('estado', true)
                        ->whereHas('asignaturaTutoria.ciclo', fn (Builder $q3) => $q3->whereIn('fk_carrera', $careerIds))
                    )
                    ->orWhereHas('paralelos.ciclos', fn (Builder $q2) => $q2->whereIn('fk_carrera', $careerIds));
            })
            ->with([
                'matriculasTitulacion' => fn ($q) => $q
                    ->where('fk_periodo', $currentPeriod->getKey()),
            ]);

        if ($search = trim((string) $request->string('search'))) {
            $like = "%{$search}%";
            $query->where(fn (Builder $q) => $q->whereLike('nombre', $like)->orWhereLike('cedula', $like)->orWhereLike('correo', $like));
        }

        if ($request->has('enrolled')) {
            $onlyEnrolled = $request->boolean('enrolled');
            if ($onlyEnrolled) {
                $query->whereHas('matriculasTitulacion', fn ($q) => $q->where('fk_periodo', $currentPeriod->getKey())->where('estado', true));
            } else {
                $query->whereDoesntHave('matriculasTitulacion', fn ($q) => $q->where('fk_periodo', $currentPeriod->getKey())->where('estado', true));
            }
        }

        $students = $query->orderBy('nombre')->paginate($request->integer('per_page', 15));

        return response()->json([
            'data' => collect($students->items())->map(function (Usuario $student) use ($currentPeriod) {
                $matricula = $student->matriculasTitulacion
                    ->where('fk_periodo', $currentPeriod->getKey())
                    ->where('estado', true)
                    ->first();

                return [
                    'id' => $student->getKey(),
                    'student_id' => $student->getKey(),
                    'identification' => $student->cedula,
                    'name' => $student->nombre,
                    'email' => $student->correo,
                    'phone' => $student->telefono,
                    'cycle_number' => $student->ciclo_actual,
                    'academic_stage' => $student->academicStage(),
                    'is_degree_enrolled' => $matricula !== null,
                    'degree_enrollment_id' => $matricula?->getKey(),
                    'enrolled_at' => $matricula?->fecha_matricula?->toDateString(),
                    'period_id' => $currentPeriod->getKey(),
                    'period_name' => $currentPeriod->nombre,
                ];
            }),
            'meta' => [
                'current_page' => $students->currentPage(),
                'last_page' => $students->lastPage(),
                'per_page' => $students->perPage(),
                'from' => $students->firstItem(),
                'to' => $students->lastItem(),
                'total' => $students->total(),
                'current_period' => [
                    'id' => $currentPeriod->getKey(),
                    'name' => $currentPeriod->nombre,
                ],
            ],
        ]);
    }

    public function enroll(Request $request, Usuario $student): JsonResponse
    {
        /** @var Usuario $user */
        $user = $request->user();
        $this->authorizeCoordinator($user);
        abort_unless($student->hasRole('estudiante') && $student->estado, 422, 'El usuario no es un estudiante activo.');
        // Sin ciclo registrado se conserva el comportamiento anterior.
        abort_if($student->academicStage() === Usuario::STAGE_TUTORING, 422, 'Solo se matriculan en titulación los estudiantes del último ciclo de su carrera.');

        $currentPeriod = PeriodoAcademico::query()->where('estado', true)->firstOrFail();

        $careerIds = $this->getCoordinatorCareerIds($user);
        $studentCareerMatches = false;
        if ($student->fk_carrera && $careerIds->contains($student->fk_carrera)) {
            $studentCareerMatches = true;
        } elseif ($student->inscripciones()->where('estado', true)->whereHas('asignaturaTutoria.ciclo', fn (Builder $q) => $q->whereIn('fk_carrera', $careerIds))->exists()) {
            $studentCareerMatches = true;
        } elseif ($student->paralelos()->whereHas('ciclos', fn (Builder $q) => $q->whereIn('fk_carrera', $careerIds))->exists()) {
            $studentCareerMatches = true;
        }

        abort_unless($studentCareerMatches, 422, 'El estudiante no pertenece a la carrera de esta coordinación.');

        $careerId = $student->fk_carrera
            ?? $careerIds->first()
            ?? Carrera::value('id_carrera');

        $matricula = MatriculaTitulacion::query()->updateOrCreate(
            [
                'fk_estudiante' => $student->getKey(),
                'fk_periodo' => $currentPeriod->getKey(),
            ],
            [
                'fk_carrera' => $careerId,
                'fecha_matricula' => today(),
                'estado' => true,
            ]
        );

        return response()->json([
            'message' => 'Estudiante matriculado en titulación exitosamente.',
            'data' => [
                'id' => $student->getKey(),
                'student_id' => $student->getKey(),
                'is_degree_enrolled' => true,
                'degree_enrollment_id' => $matricula->getKey(),
                'enrolled_at' => $matricula->fecha_matricula->toDateString(),
                'period_id' => $currentPeriod->getKey(),
                'period_name' => $currentPeriod->nombre,
            ],
        ]);
    }

    public function unenroll(Request $request, Usuario $student): JsonResponse
    {
        /** @var Usuario $user */
        $user = $request->user();
        $this->authorizeCoordinator($user);

        $currentPeriod = PeriodoAcademico::query()->where('estado', true)->firstOrFail();

        $matricula = MatriculaTitulacion::query()
            ->where('fk_estudiante', $student->getKey())
            ->where('fk_periodo', $currentPeriod->getKey())
            ->first();

        if ($matricula) {
            $matricula->update(['estado' => false]);
        }

        return response()->json([
            'message' => 'Estudiante dado de baja de titulación.',
            'data' => [
                'student_id' => $student->getKey(),
                'is_degree_enrolled' => false,
                'degree_enrollment_id' => null,
                'period_id' => $currentPeriod->getKey(),
                'period_name' => $currentPeriod->nombre,
            ],
        ]);
    }
}

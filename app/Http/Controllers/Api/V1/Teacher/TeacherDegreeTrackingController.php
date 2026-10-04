<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DegreeTopicResource;
use App\Models\ActividadAvance;
use App\Models\AsignacionDocente;
use App\Models\FichaSeguimiento;
use App\Models\TemaTitulacion;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class TeacherDegreeTrackingController extends Controller
{
    /**
     * Lista los temas de titulación asignados al docente con su seguimiento.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var Usuario $user */
        $user = $request->user();
        abort_unless($user->estado && $user->hasAnyRole(['docente', 'administrador']), Response::HTTP_FORBIDDEN);

        $teacherId = $user->getKey();
        $query = TemaTitulacion::query()
            ->where('estado', 'aprobado')
            ->whereHas('activeAssignments', function (Builder $q) use ($teacherId, $request): void {
                $q->where('fk_id_usuario', $teacherId);
                if ($request->filled('role')) {
                    $q->where('rol', $request->input('role'));
                }
            })
            ->with(TemaTitulacion::REVIEW_RELATIONS);

        if ($request->filled('search')) {
            $search = '%'.$request->string('search').'%';
            $query->where(function (Builder $q) use ($search): void {
                $q->whereLike('titulo', $search)
                    ->orWhereHas('estudiante', fn (Builder $sq) => $sq->whereLike('nombre', $search));
            });
        }

        return DegreeTopicResource::collection(
            $query->orderByDesc('id_tema_tit')->paginate($request->integer('per_page', 15))
        );
    }

    /**
     * Muestra el detalle de seguimiento de un tema asignado.
     */
    public function show(Request $request, TemaTitulacion $topic): DegreeTopicResource
    {
        $this->authorizeTeacherOnTopic($request->user(), $topic);

        $topic->load(TemaTitulacion::REVIEW_RELATIONS);

        return DegreeTopicResource::make($topic);
    }

    /**
     * Registra una nueva tarea/actividad de avance por parte del docente.
     */
    public function storeActivity(Request $request, TemaTitulacion $topic): JsonResponse
    {
        /** @var Usuario $teacher */
        $teacher = $request->user();
        $assignment = $this->authorizeTeacherOnTopic($teacher, $topic);

        $request->validate([
            'descripcion' => ['required', 'string', 'max:1000'],
            'completada' => ['nullable', 'boolean'],
        ]);

        $ficha = FichaSeguimiento::query()->firstOrCreate(
            ['fk_tema_tit' => $topic->getKey()],
            ['fecha_apertura' => now()->toDateString(), 'porcentaje_avance' => 0.00, 'estado' => 'en_progreso']
        );

        $activity = ActividadAvance::query()->create([
            'fk_ficha' => $ficha->getKey(),
            'fk_docente' => $teacher->getKey(),
            'descripcion' => $request->string('descripcion')->value(),
            'completada' => $request->boolean('completada'),
            'fecha_registro' => now()->toDateString(),
        ]);

        $total = $ficha->actividades()->count();
        $done = $ficha->actividades()->where('completada', true)->count();
        if ($total > 0) {
            $ficha->update(['porcentaje_avance' => round(($done / $total) * 100, 2)]);
        }

        return response()->json([
            'message' => 'Actividad de seguimiento registrada exitosamente.',
            'data' => [
                'id' => $activity->getKey(),
                'description' => $activity->descripcion,
                'is_completed' => (bool) $activity->completada,
                'registered_at' => $activity->fecha_registro?->toDateString(),
                'progress_percentage' => (float) $ficha->fresh()->porcentaje_avance,
                'teacher' => [
                    'id' => $teacher->getKey(),
                    'name' => $teacher->nombre,
                    'email' => $teacher->correo,
                    'role' => $assignment->rol,
                ],
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Alterna o marca como completada/pendiente una tarea de avance.
     */
    public function toggleActivity(Request $request, TemaTitulacion $topic, ActividadAvance $activity): JsonResponse
    {
        $teacher = $request->user();
        $this->authorizeTeacherOnTopic($teacher, $topic);

        $ficha = $topic->fichaSeguimiento ?: FichaSeguimiento::query()->firstOrCreate(
            ['fk_tema_tit' => $topic->getKey()],
            ['fecha_apertura' => now()->toDateString(), 'porcentaje_avance' => 0.00, 'estado' => 'en_progreso']
        );

        abort_if($activity->fk_ficha !== $ficha->getKey(), Response::HTTP_BAD_REQUEST, 'La actividad no corresponde a este tema.');

        if ($activity->fk_docente !== null && $activity->fk_docente !== $teacher->getKey()) {
            abort(Response::HTTP_FORBIDDEN, 'Solo el docente que subió esta tarea puede marcarla como completada.');
        }

        $activity->update([
            'completada' => $request->has('completada') ? $request->boolean('completada') : ! $activity->completada,
        ]);

        $total = $ficha->actividades()->count();
        $done = $ficha->actividades()->where('completada', true)->count();
        $progress = $total > 0 ? round(($done / $total) * 100, 2) : 0.00;
        $ficha->update(['porcentaje_avance' => $progress]);

        return response()->json([
            'message' => 'Estado de la actividad actualizado.',
            'data' => [
                'id' => $activity->getKey(),
                'is_completed' => (bool) $activity->completada,
                'progress_percentage' => (float) $ficha->fresh()->porcentaje_avance,
            ],
        ]);
    }

    /**
     * Actualiza el porcentaje de avance de la ficha.
     */
    public function updateProgress(Request $request, TemaTitulacion $topic): JsonResponse
    {
        $this->authorizeTeacherOnTopic($request->user(), $topic);

        $request->validate([
            'porcentaje_avance' => ['required', 'numeric', 'min:0', 'max:100'],
            'estado' => ['nullable', 'string', 'max:50'],
        ]);

        $ficha = FichaSeguimiento::query()->firstOrCreate(
            ['fk_tema_tit' => $topic->getKey()],
            ['fecha_apertura' => now()->toDateString(), 'porcentaje_avance' => 0.00, 'estado' => 'en_progreso']
        );

        $data = ['porcentaje_avance' => $request->float('porcentaje_avance')];
        if ($request->filled('estado')) {
            $data['estado'] = $request->string('estado')->value();
        }
        $ficha->update($data);

        return response()->json([
            'message' => 'Porcentaje de avance actualizado.',
            'data' => [
                'id' => $ficha->getKey(),
                'progress_percentage' => (float) $ficha->fresh()->porcentaje_avance,
                'status' => $ficha->estado,
            ],
        ]);
    }

    /**
     * Valida que el usuario sea docente con asignación activa en el tema.
     */
    private function authorizeTeacherOnTopic(Usuario $user, TemaTitulacion $topic): AsignacionDocente
    {
        abort_unless($user->estado && $user->hasAnyRole(['docente', 'administrador']), Response::HTTP_FORBIDDEN);

        $assignment = $topic->activeAssignments()->where('fk_id_usuario', $user->getKey())->first();
        abort_unless($assignment !== null, Response::HTTP_FORBIDDEN, 'No tienes asignación docente activa en este tema.');

        return $assignment;
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Coordination;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TeacherResource;
use App\Models\TemaTitulacion;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TeacherCoordinationController extends Controller
{
    /**
     * Lista todos los docentes registrados y activos en el sistema para asignación.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', TemaTitulacion::class);

        $query = Usuario::query()
            ->whereHas('roles', fn (Builder $q) => $q->where('slug', 'docente'))
            ->where('estado', true)
            ->with([
                'teachingCareers' => fn ($q) => $q->with('facultad')->where('carrera.estado', true),
            ])
            ->withCount([
                'asignacionesDocente as tutor_assignments_count' => fn (Builder $q) => $q->where('rol', 'tutor')->where('estado', true),
                'asignacionesDocente as peer_assignments_count' => fn (Builder $q) => $q->where('rol', 'par_academico')->where('estado', true),
            ]);

        if ($search = trim((string) $request->string('search'))) {
            $terms = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [$search];
            foreach ($terms as $term) {
                $query->where(function (Builder $inner) use ($term): void {
                    $inner->whereLike('nombre', "%{$term}%")
                        ->orWhereLike('correo', "%{$term}%")
                        ->orWhereLike('cedula', "%{$term}%")
                        ->orWhereHas('teachingCareers', function (Builder $cq) use ($term): void {
                            $cq->whereLike('carrera.nombre', "%{$term}%")
                                ->orWhereHas('facultad', fn (Builder $fq) => $fq->whereLike('facultad.nombre', "%{$term}%"));
                        });
                });
            }
        }

        if ($careerId = $request->integer('career_id')) {
            $query->whereHas('teachingCareers', fn (Builder $q) => $q->where('carrera.id_carrera', $careerId));
        }

        if ($facultyId = $request->integer('faculty_id')) {
            $query->whereHas('teachingCareers.facultad', fn (Builder $q) => $q->where('facultad.id_facultad', $facultyId));
        }

        return TeacherResource::collection(
            $query->orderBy('nombre')->get()
        );
    }
}

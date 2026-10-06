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
     * Lista los docentes activos de todas las carreras y facultades para
     * asignarlos como tutor o par académico: la coordinación de titulación no
     * se limita a su propia facultad. Cada palabra de `search` debe aparecer en
     * el nombre, correo, cédula, carrera o facultad del docente.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', TemaTitulacion::class);

        $query = Usuario::query()
            ->whereHas('roles', fn (Builder $q) => $q->where('slug', 'docente'))
            ->where('estado', true)
            ->with(['teachingCareers.facultad', 'carrera.facultad'])
            ->withCount([
                'asignacionesDocente as tutor_assignments_count' => fn (Builder $q) => $q->where('rol', 'tutor')->where('estado', true),
                'asignacionesDocente as peer_assignments_count' => fn (Builder $q) => $q->where('rol', 'par_academico')->where('estado', true),
            ]);

        foreach (preg_split('/\s+/', trim((string) $request->string('search')), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $term) {
            $like = "%{$term}%";
            $query->where(function (Builder $inner) use ($like): void {
                $inner->whereLike('nombre', $like)
                    ->orWhereLike('correo', $like)
                    ->orWhereLike('cedula', $like)
                    ->orWhereHas('teachingCareers', fn (Builder $career) => $career->whereLike('carrera.nombre', $like)
                        ->orWhereHas('facultad', fn (Builder $faculty) => $faculty->whereLike('nombre', $like)))
                    ->orWhereHas('carrera', fn (Builder $career) => $career->whereLike('nombre', $like)
                        ->orWhereHas('facultad', fn (Builder $faculty) => $faculty->whereLike('nombre', $like)));
            });
        }

        return TeacherResource::collection(
            $query->orderBy('nombre')->get()
        );
    }
}

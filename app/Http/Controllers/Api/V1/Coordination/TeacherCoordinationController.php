<?php

namespace App\Http\Controllers\Api\V1\Coordination;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TeacherResource;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TeacherCoordinationController extends Controller
{
    /**
     * Lista todos los docentes registrados y activos en el sistema para asignación.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        if (! $user || (! $user->hasRole('coordinador_titulacion') && ! $user->hasRole('administrador'))) {
            abort(Response::HTTP_FORBIDDEN, 'No autorizado para consultar el catálogo de docentes.');
        }

        $query = Usuario::query()
            ->whereHas('roles', fn (Builder $q) => $q->where('slug', 'docente'))
            ->where('estado', true)
            ->withCount([
                'asignacionesDocente as tutor_assignments_count' => fn (Builder $q) => $q->where('rol', 'tutor')->where('estado', true),
                'asignacionesDocente as peer_assignments_count' => fn (Builder $q) => $q->where('rol', 'par_academico')->where('estado', true),
            ]);

        if ($search = trim((string) $request->string('search'))) {
            $query->where(function (Builder $inner) use ($search): void {
                $inner->whereLike('nombre', "%{$search}%")
                    ->orWhereLike('correo', "%{$search}%")
                    ->orWhereLike('cedula', "%{$search}%");
            });
        }

        return TeacherResource::collection(
            $query->orderBy('nombre')->get()
        );
    }
}

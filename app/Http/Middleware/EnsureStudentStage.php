<?php

namespace App\Http\Middleware;

use App\Models\Usuario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un estudiante del último ciclo de su carrera solo accede a titulación; los
 * de ciclos anteriores, solo a tutorías. Sin ciclo registrado se conserva el
 * acceso anterior.
 */
class EnsureStudentStage
{
    public function handle(Request $request, Closure $next, string $stage): Response
    {
        $user = $request->user();
        $current = $user instanceof Usuario ? $user->academicStage() : null;

        abort_if($current !== null && $current !== $stage, Response::HTTP_FORBIDDEN, $stage === Usuario::STAGE_DEGREE
            ? 'La titulación está disponible solo en el último ciclo de la carrera.'
            : 'En el último ciclo de la carrera el estudiante solo accede a titulación.');

        return $next($request);
    }
}

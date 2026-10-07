<?php

namespace App\Http\Middleware;

use App\Models\Usuario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Las cuentas creadas por carga masiva deben completar sus datos y cambiar la
 * contraseña provisional antes de usar el resto de la API.
 */
class EnsureProfileCompleted
{
    private const ALLOWED_ROUTES = [
        'api.v1.auth.user',
        'api.v1.auth.logout',
        'api.v1.auth.profile.complete',
    ];

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum');

        if ($user instanceof Usuario && $user->must_complete_profile && ! $request->routeIs(...self::ALLOWED_ROUTES)) {
            return response()->json([
                'message' => 'Debes completar tus datos y cambiar la contraseña provisional antes de continuar.',
                'code' => 'profile_incomplete',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}

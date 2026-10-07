<?php

use App\Http\Middleware\EnsureProfileCompleted;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies();
        $middleware->redirectGuestsTo(fn (Request $request) => null);

        // CAL-005 & CAL-008: Security headers and internal healthcheck protection
        $middleware->append(SecurityHeaders::class);

        // Las cuentas importadas deben completar su perfil antes de usar la API.
        $middleware->appendToGroup('api', EnsureProfileCompleted::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // CAL-004: Return 401 for unauthenticated API requests even without Accept header
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'No autenticado.',
                ], 401);
            }
        });

        // CAL-006: Prevent internal Eloquent model name leaking in 404 responses
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Recurso no encontrado.',
                ], 404);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Recurso no encontrado.',
                ], 404);
            }
        });
    })->create();

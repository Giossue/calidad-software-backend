<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Usuario;
use Illuminate\Http\Request;

class TourController extends Controller
{
    /** Límite de recorridos guardados por usuario: hay uno por pantalla del sistema. */
    private const MAX_TOURS = 100;

    /** Registra que el usuario ya vio un recorrido del tutorial. */
    public function complete(Request $request): UserResource
    {
        $tour = $request->validate([
            'tour' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9-]+(\/[a-z0-9-]+)?$/'],
        ], [], ['tour' => 'recorrido'])['tour'];

        /** @var Usuario $user */
        $user = $request->user();
        $completed = $user->completed_tours ?? [];

        if (! in_array($tour, $completed, true) && count($completed) < self::MAX_TOURS) {
            $user->forceFill(['completed_tours' => [...$completed, $tour]])->save();
        }

        return UserResource::make($user->load(['roles', 'carrera.facultad', 'ciclo.paralelo']));
    }
}

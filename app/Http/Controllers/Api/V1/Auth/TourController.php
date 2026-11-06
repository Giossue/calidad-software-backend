<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Usuario;
use Illuminate\Http\Request;

class TourController extends Controller
{
    /** Registra que el usuario ya vio el tutorial; conserva la primera fecha. */
    public function complete(Request $request): UserResource
    {
        /** @var Usuario $user */
        $user = $request->user();

        if ($user->tour_completed_at === null) {
            $user->forceFill(['tour_completed_at' => now()])->save();
        }

        return UserResource::make($user->load(['roles', 'carrera.facultad', 'ciclo.paralelo']));
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\CompleteProfileRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Ciclo;
use App\Models\Usuario;
use App\Support\StudentCycle;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class CompleteProfileController extends Controller
{
    public function __invoke(CompleteProfileRequest $request, StudentCycle $cycles): UserResource
    {
        /** @var Usuario $user */
        $user = $request->user();

        DB::transaction(function () use ($request, $user, $cycles): void {
            $user->update(array_filter([
                'cedula' => $request->validated('identification'),
                'nombre' => $request->validated('name'),
                'telefono' => $request->validated('phone'),
                'password_hash' => $request->validated('password'),
                'must_complete_profile' => false,
            ], fn (mixed $value): bool => $value !== null));

            if ($request->validated('cycle_id') !== null) {
                $cycles->assign($user, Ciclo::query()->findOrFail($request->integer('cycle_id')));
            }

            if ($request->validated('password') === null) {
                return;
            }

            // Al cambiar la contraseña se cierran las demás sesiones.
            $current = $user->currentAccessToken();
            $user->tokens()
                ->when($current instanceof PersonalAccessToken, fn ($query) => $query->whereKeyNot($current->getKey()))
                ->delete();
        });

        return UserResource::make($user->refresh()->load(['roles', 'carrera.facultad', 'ciclo.paralelo']));
    }
}

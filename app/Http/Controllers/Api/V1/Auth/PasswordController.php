<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class PasswordController extends Controller
{
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink(['correo' => $request->validated('email')]);

        return response()->json([
            'message' => 'Si el correo está registrado, recibirás un enlace para restablecer la contraseña.',
        ], 202);
    }

    public function reset(ResetPasswordRequest $request, ResetsUserPasswords $resetter): JsonResponse
    {
        $status = Password::reset(
            [
                'correo' => $request->validated('email'),
                'password' => $request->validated('password'),
                'password_confirmation' => $request->validated('password_confirmation'),
                'token' => $request->validated('token'),
            ],
            function (User $user, string $password) use ($request, $resetter): void {
                $resetter->reset($user, [
                    'password' => $password,
                    'password_confirmation' => $request->validated('password_confirmation'),
                ]);
                $user->setRememberToken(Str::random(60));
                $user->save();
                $user->tokens()->delete();
                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => ['El enlace para restablecer la contraseña es inválido o expiró.'],
            ]);
        }

        return response()->json(['message' => 'La contraseña fue restablecida correctamente.']);
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\IssueUserToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\IssueTokenRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TokenController extends Controller
{
    public function store(IssueTokenRequest $request, IssueUserToken $issueUserToken): JsonResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);
        $user = Usuario::query()->whereRaw('lower(correo) = lower(?)', [$credentials['email']])->first();

        if (! $user || ! $user->estado || ! Hash::check($credentials['password'], $user->password_hash)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas no son correctas.'],
            ]);
        }

        if ($user->two_factor_confirmed_at !== null) {
            $deviceName = $request->validated('device_name');
            $challengeName = 'two-factor-challenge:'.$deviceName;
            $user->tokens()->where('name', $challengeName)->delete();
            $challenge = $user->createToken(
                $challengeName,
                ['two-factor:challenge'],
                now()->addMinutes(5),
            );

            return response()->json([
                'message' => 'Esta cuenta requiere el desafío de autenticación de dos factores.',
                'code' => 'two_factor_required',
                'data' => [
                    'challenge_token' => $challenge->plainTextToken,
                    'expires_at' => now()->addMinutes(5)->toISOString(),
                ],
            ], Response::HTTP_CONFLICT);
        }

        return response()->json([
            'data' => $issueUserToken->handle($user, $request->validated('device_name')),
        ]);
    }

    public function show(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }

    public function destroy(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}

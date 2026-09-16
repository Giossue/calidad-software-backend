<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\IssueUserToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\TwoFactorChallengeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

class TwoFactorChallengeController extends Controller
{
    public function store(
        TwoFactorChallengeRequest $request,
        TwoFactorAuthenticationProvider $provider,
        IssueUserToken $issueUserToken,
    ): JsonResponse {
        $user = $request->user();
        $accessToken = $user->currentAccessToken();

        if (! $accessToken->can('two-factor:challenge') || ! str_starts_with($accessToken->name, 'two-factor-challenge:')) {
            abort(403);
        }

        $valid = false;
        $recoveryCode = $request->validated('recovery_code');

        if (is_string($recoveryCode) && in_array($recoveryCode, $user->recoveryCodes(), true)) {
            $user->replaceRecoveryCode($recoveryCode);
            $valid = true;
        }

        $code = $request->validated('code');
        if (is_string($code) && $provider->verify(
            Fortify::currentEncrypter()->decrypt($user->two_factor_secret),
            $code,
        )) {
            $valid = true;
        }

        if (! $valid) {
            throw ValidationException::withMessages([
                'code' => ['El código de autenticación no es válido.'],
            ]);
        }

        $deviceName = str($accessToken->name)->after('two-factor-challenge:')->toString();
        $accessToken->delete();

        return response()->json(['data' => $issueUserToken->handle($user, $deviceName)]);
    }
}

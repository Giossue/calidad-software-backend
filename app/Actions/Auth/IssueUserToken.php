<?php

namespace App\Actions\Auth;

use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Support\Carbon;

class IssueUserToken
{
    /** @return array{access_token: string, token_type: string, expires_at: string|null, user: UserResource} */
    public function handle(User $user, string $deviceName): array
    {
        $user->tokens()->where('name', $deviceName)->delete();

        $expiration = config('sanctum.expiration');
        $expiresAt = is_int($expiration) && $expiration > 0
            ? Carbon::now()->addMinutes($expiration)
            : null;
        $token = $user->createToken($deviceName, ['*'], $expiresAt);

        return [
            'access_token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt?->toISOString(),
            'user' => UserResource::make($user),
        ];
    }
}

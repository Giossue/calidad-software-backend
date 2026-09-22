<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\IssueUserToken;
use App\Actions\Fortify\CreateNewUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class RegistrationController extends Controller
{
    public function store(
        RegisterRequest $request,
        CreateNewUser $creator,
        IssueUserToken $issueUserToken,
    ): JsonResponse {
        $user = $creator->create($request->safe()->only([
            'identification', 'name', 'email', 'password', 'password_confirmation',
        ]));

        event(new Registered($user));

        return response()->json([
            'data' => $issueUserToken->handle($user, $request->validated('device_name')),
        ], Response::HTTP_CREATED);
    }
}

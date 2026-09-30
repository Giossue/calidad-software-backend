<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AssignCoordinatorCareersRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Usuario;
use Illuminate\Validation\ValidationException;

class CoordinatorCareerAssignmentController extends Controller
{
    public function update(AssignCoordinatorCareersRequest $request, Usuario $user): UserResource
    {
        if (! $user->hasRole('coordinador_carrera')) {
            throw ValidationException::withMessages(['user' => 'El usuario debe tener el rol de coordinador de carrera.']);
        }

        $user->coordinatedCareers()->sync($request->validated('career_ids'));

        return UserResource::make($user->load('roles', 'coordinatedCareers'));
    }
}

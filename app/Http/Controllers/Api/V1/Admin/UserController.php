<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreUserRequest;
use App\Http\Requests\Api\V1\Admin\UpdateUserRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Usuario::class);

        return UserResource::collection(
            Usuario::query()->orderBy('nombre')->orderBy('id_usuario')->get(),
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = Usuario::create([
            'cedula' => $request->validated('identification'),
            'nombre' => $request->validated('name'),
            'correo' => $request->validated('email'),
            'telefono' => $request->validated('phone'),
            'password_hash' => $request->validated('password'),
            'rol' => $request->validated('role'),
        ])->refresh();

        return response()->json([
            'data' => new UserResource($user),
        ], Response::HTTP_CREATED);
    }

    public function update(UpdateUserRequest $request, Usuario $user): JsonResponse
    {
        $columns = [
            'identification' => 'cedula',
            'name' => 'nombre',
            'email' => 'correo',
            'phone' => 'telefono',
            'role' => 'rol',
            'password' => 'password_hash',
        ];

        $attributes = [];
        foreach ($columns as $field => $column) {
            if ($request->exists($field)) {
                $attributes[$column] = $request->validated($field);
            }
        }

        $user->fill($attributes)->save();

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    public function deactivate(Usuario $user): JsonResponse
    {
        Gate::authorize('deactivate', $user);
        $user->estado = false;
        $user->save();

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }
}

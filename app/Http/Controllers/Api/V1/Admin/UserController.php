<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreUserRequest;
use App\Http\Requests\Api\V1\Admin\UpdateUserRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Usuario;
use App\Notifications\ProvisionalPasswordNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
        // Generar contraseña provisional aleatoria segura (ej. Abc123xyz!)
        $provisionalPassword = Str::random(6) . rand(10, 99) . '!';

        $user = Usuario::create([
            'cedula' => $request->validated('identification'),
            'nombre' => $request->validated('name'),
            'correo' => $request->validated('email'),
            'telefono' => $request->validated('phone'),
            'password_hash' => Hash::make($provisionalPassword),
            'rol' => $request->validated('role'),
            'estado' => true,
            'email_verified_at' => now(),
        ])->refresh();

        // Enviar notificación con la contraseña provisional por correo electrónico
        try {
            $user->notify(new ProvisionalPasswordNotification($provisionalPassword));
        } catch (\Throwable $e) {
            // Silencioso si no está configurado el servidor SMTP en local
        }

        return response()->json([
            'data' => new UserResource($user),
            'message' => "Usuario creado exitosamente. Se envió la contraseña provisional ({$provisionalPassword}) al correo.",
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

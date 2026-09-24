<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreUserRequest;
use App\Http\Requests\Api\V1\Admin\UpdateUserRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Role;
use App\Models\Usuario;
use App\Notifications\ProvisionalPasswordNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Usuario::class);

        $query = Usuario::query()->with('roles');

        if ($search = trim((string) $request->string('search'))) {
            $query->where(function (Builder $inner) use ($search) {
                $inner->whereLike('nombre', "%{$search}%")
                    ->orWhereLike('correo', "%{$search}%")
                    ->orWhereLike('cedula', "%{$search}%");
            });
        }

        if ($role = trim((string) $request->string('role'))) {
            $query->whereHas('roles', fn (Builder $inner) => $inner->where('slug', $role));
        }

        return UserResource::collection(
            $query->orderBy('nombre')->orderBy('id_usuario')->paginate($request->integer('per_page', 15)),
        )->additional(['meta' => [
            'active_count' => Usuario::query()->where('estado', true)->count(),
            'inactive_count' => Usuario::query()->where('estado', false)->count(),
            'admin_count' => Usuario::query()->whereHas('roles', fn (Builder $inner) => $inner->where('slug', 'administrador'))->count(),
            'teacher_count' => Usuario::query()->whereHas('roles', fn (Builder $inner) => $inner->where('slug', 'docente'))->count(),
            'student_count' => Usuario::query()->whereHas('roles', fn (Builder $inner) => $inner->where('slug', 'estudiante'))->count(),
        ]]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $provisionalPassword = Str::password(20, true, true, true, false);

        $user = DB::transaction(function () use ($request, $provisionalPassword): Usuario {
            $user = Usuario::query()->create([
                'cedula' => $request->validated('identification'),
                'nombre' => $request->validated('name'),
                'correo' => $request->validated('email'),
                'telefono' => $request->validated('phone'),
                'password_hash' => Hash::make($provisionalPassword),
                'estado' => true,
                'email_verified_at' => now(),
            ])->refresh();

            $user->roles()->sync(Role::query()->where('slug', $request->validated('role'))->value('id'));

            $user->notify(new ProvisionalPasswordNotification($provisionalPassword));

            return $user;
        });

        $user->load('roles');

        return response()->json([
            'data' => new UserResource($user),
            'message' => 'Usuario creado. Revisa el correo registrado para obtener la contraseña provisional.',
        ], Response::HTTP_CREATED);
    }

    public function update(UpdateUserRequest $request, Usuario $user): JsonResponse
    {
        $columns = [
            'identification' => 'cedula',
            'name' => 'nombre',
            'email' => 'correo',
            'phone' => 'telefono',
            'password' => 'password_hash',
        ];

        $attributes = [];
        foreach ($columns as $field => $column) {
            if ($request->exists($field)) {
                $attributes[$column] = $request->validated($field);
            }
        }

        $user->fill($attributes)->save();

        if ($request->exists('role')) {
            $user->roles()->sync(Role::query()->where('slug', $request->validated('role'))->value('id'));
        }

        return response()->json([
            'data' => new UserResource($user->load('roles')),
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

    public function activate(Usuario $user): JsonResponse
    {
        Gate::authorize('activate', $user);
        $user->estado = true;
        $user->save();

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }
}

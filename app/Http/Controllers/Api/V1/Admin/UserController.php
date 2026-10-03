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

        $query = Usuario::query()->with([
            'roles',
            'carrera.facultad',
            'coordinatedCareers.facultad',
            'teachingCareers.facultad',
        ]);

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
            $roleSlug = $request->validated('role');
            $careerId = $roleSlug !== 'administrador' ? $request->validated('career_id') : null;

            $user = Usuario::query()->create([
                'cedula' => $request->validated('identification'),
                'nombre' => $request->validated('name'),
                'correo' => $request->validated('email'),
                'telefono' => $request->validated('phone'),
                'password_hash' => Hash::make($provisionalPassword),
                'estado' => true,
                'email_verified_at' => now(),
                'fk_carrera' => $careerId,
            ])->refresh();

            $user->roles()->sync(Role::query()->where('slug', $roleSlug)->value('id'));

            if ($careerId) {
                if ($roleSlug === 'coordinador_carrera') {
                    $user->coordinatedCareers()->syncWithoutDetaching([$careerId => ['assigned_at' => now()]]);
                } elseif ($roleSlug === 'docente') {
                    $user->teachingCareers()->syncWithoutDetaching([$careerId => ['assigned_at' => now()]]);
                }
            }

            $user->notify(new ProvisionalPasswordNotification($provisionalPassword));

            return $user;
        });

        $user->load(['roles', 'carrera.facultad', 'coordinatedCareers.facultad', 'teachingCareers.facultad']);

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

        $roleSlug = $request->exists('role') ? $request->validated('role') : $user->roles->first()?->slug;

        if ($roleSlug === 'administrador') {
            $attributes['fk_carrera'] = null;
        } elseif ($request->exists('career_id')) {
            $attributes['fk_carrera'] = $request->validated('career_id');
        }

        $user->fill($attributes)->save();

        if ($request->exists('role')) {
            $user->roles()->sync(Role::query()->where('slug', $roleSlug)->value('id'));
        }

        $careerId = $user->fk_carrera;
        if ($careerId) {
            if ($roleSlug === 'coordinador_carrera') {
                $user->coordinatedCareers()->syncWithoutDetaching([$careerId => ['assigned_at' => now()]]);
            } elseif ($roleSlug === 'docente') {
                $user->teachingCareers()->syncWithoutDetaching([$careerId => ['assigned_at' => now()]]);
            }
        }

        return response()->json([
            'data' => new UserResource($user->load(['roles', 'carrera.facultad', 'coordinatedCareers.facultad', 'teachingCareers.facultad'])),
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

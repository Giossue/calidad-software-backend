<?php

namespace App\Actions\Users;

use App\Models\Role;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateAccount
{
    /**
     * Crea la cuenta con su rol y carrera. Devuelve el usuario y la contraseña
     * provisional, que el llamador envía por correo.
     *
     * @param  array{identification: string|null, name: string, email: string, phone: string|null, role: string, career_id: int|null, must_complete_profile?: bool}  $data
     * @return array{0: Usuario, 1: string}
     */
    public function handle(array $data): array
    {
        $provisionalPassword = Str::password(20, true, true, true, false);
        $careerId = $data['role'] !== 'administrador' ? $data['career_id'] : null;

        $user = Usuario::query()->create([
            'cedula' => $data['identification'],
            'nombre' => $data['name'],
            'correo' => $data['email'],
            'telefono' => $data['phone'],
            'password_hash' => Hash::make($provisionalPassword),
            'estado' => true,
            'email_verified_at' => now(),
            'fk_carrera' => $careerId,
            'must_complete_profile' => $data['must_complete_profile'] ?? false,
        ])->refresh();

        $user->roles()->sync(Role::query()->where('slug', $data['role'])->valueOrFail('id'));

        if ($careerId) {
            if ($data['role'] === 'coordinador_carrera') {
                $user->coordinatedCareers()->syncWithoutDetaching([$careerId => ['assigned_at' => now()]]);
            } elseif ($data['role'] === 'docente') {
                $user->teachingCareers()->syncWithoutDetaching([$careerId => ['assigned_at' => now()]]);
            }
        }

        return [$user, $provisionalPassword];
    }

    /** Nombre provisional a partir del correo (ana.torres@… → Ana Torres) hasta que el usuario lo complete. */
    public static function provisionalName(string $email): string
    {
        $local = Str::before($email, '@');
        $words = trim((string) preg_replace('/[^\pL]+/u', ' ', $local));

        return $words === '' ? 'Usuario' : Str::title(Str::limit($words, 150, ''));
    }
}

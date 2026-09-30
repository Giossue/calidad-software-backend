<?php

namespace App\Actions\Tutoring;

use App\Models\Role;
use App\Models\Usuario;
use App\Notifications\ProvisionalPasswordNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ManageTeacher
{
    /** @param array{career_id: int, identification: string, name: string, email: string, phone: string} $data */
    public function create(array $data): Usuario
    {
        $provisionalPassword = Str::password(20, true, true, true, false);

        return DB::transaction(function () use ($data, $provisionalPassword): Usuario {
            $teacher = Usuario::query()->create([
                'cedula' => $data['identification'],
                'nombre' => trim($data['name']),
                'correo' => strtolower(trim($data['email'])),
                'telefono' => $data['phone'],
                'password_hash' => Hash::make($provisionalPassword),
                'estado' => true,
                'email_verified_at' => now(),
            ]);
            $teacher->roles()->sync(Role::query()->where('slug', 'docente')->valueOrFail('id'));
            $teacher->teachingCareers()->syncWithoutDetaching([$data['career_id']]);
            $teacher->notify(new ProvisionalPasswordNotification($provisionalPassword));

            return $teacher->load('roles', 'teachingCareers');
        });
    }

    /** @param array{identification?: string, name?: string, email?: string, phone?: string} $data */
    public function update(Usuario $teacher, array $data): Usuario
    {
        $columns = [
            'identification' => 'cedula',
            'name' => 'nombre',
            'email' => 'correo',
            'phone' => 'telefono',
        ];
        foreach ($columns as $field => $column) {
            if (array_key_exists($field, $data)) {
                $value = trim($data[$field]);
                $teacher->$column = $field === 'email' ? strtolower($value) : $value;
            }
        }
        $teacher->save();

        return $teacher->refresh()->load('roles', 'teachingCareers');
    }

    public function deactivate(Usuario $teacher): Usuario
    {
        $teacher->update(['estado' => false]);
        $teacher->tokens()->delete();

        return $teacher->refresh()->load('roles', 'teachingCareers');
    }
}

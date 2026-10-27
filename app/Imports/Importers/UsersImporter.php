<?php

namespace App\Imports\Importers;

use App\Imports\CsvReader;
use App\Models\Role;
use App\Models\Usuario;
use Illuminate\Validation\ValidationException;

class UsersImporter extends AccountImporter
{
    /** Facultad y carrera son obligatorias salvo para administradores; el ciclo, para estudiantes. */
    protected function requiredColumns(): array
    {
        return ['rol'];
    }

    public function example(): array
    {
        return self::exampleRow([
            'correo' => 'ana.torres@ueb.edu.ec', 'cedula' => '0201234567', 'nombres' => 'Ana María', 'apellidos' => 'Torres Vera',
            'rol' => 'estudiante', 'facultad' => 'Ciencias Administrativas', 'carrera' => 'Software', 'ciclo' => '1', 'telefono' => '0991234567',
        ]);
    }

    public function authorize(Usuario $user): bool
    {
        return $user->can('create', Usuario::class);
    }

    /** @param  array<string, string|null>  $row */
    protected function role(array $row): string
    {
        $role = CsvReader::normalizeHeader((string) $row['rol']);
        $slugs = Role::query()->pluck('slug')->all();

        if (! in_array($role, $slugs, true)) {
            throw ValidationException::withMessages([
                'rol' => 'El rol «'.$row['rol'].'» no es válido. Usa: '.implode(', ', $slugs).'.',
            ]);
        }

        return $role;
    }
}

<?php

namespace App\Imports\Importers;

use App\Imports\CsvReader;
use App\Models\Role;
use App\Models\Usuario;
use Illuminate\Validation\ValidationException;

class UsersImporter extends AccountImporter
{
    public function columns(): array
    {
        return ['correo' => true, 'rol' => true, 'carrera' => false, 'cedula' => false, 'nombre' => false, 'telefono' => false];
    }

    public function example(): array
    {
        return ['correo' => 'ana.torres@ueb.edu.ec', 'rol' => 'estudiante', 'carrera' => 'Software', 'cedula' => '', 'nombre' => '', 'telefono' => ''];
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

    /** @param  array<string, string|null>  $row */
    protected function careerId(array $row, Usuario $user, string $role): ?int
    {
        return $role === 'administrador' ? null : $this->lookup->careerId($row['carrera']);
    }
}

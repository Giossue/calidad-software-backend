<?php

namespace App\Imports\Importers;

use App\Models\AsignaturaTutoria;
use App\Models\Usuario;
use App\Support\TutoringCoordinatorAccess;

class StudentsImporter extends AccountImporter
{
    protected function requiredColumns(): array
    {
        return ['facultad', 'carrera', 'ciclo'];
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
        return $user->can('viewAny', AsignaturaTutoria::class);
    }

    /** @param  array<string, string|null>  $row */
    protected function role(array $row): string
    {
        return self::assertRole($row, 'estudiante');
    }

    protected function authorizeCareer(Usuario $user, int $careerId): void
    {
        app(TutoringCoordinatorAccess::class)->authorizeCareer($user, $careerId);
    }

    protected function extraRules(): array
    {
        return ['correo' => ['ends_with:@ueb.edu.ec']];
    }
}

<?php

namespace App\Imports\Importers;

use App\Models\Usuario;
use App\Support\TutoringCoordinatorAccess;

class TeachersImporter extends AccountImporter
{
    protected function requiredColumns(): array
    {
        return ['facultad', 'carrera'];
    }

    public function example(): array
    {
        return self::exampleRow([
            'correo' => 'luis.mora@ueb.edu.ec', 'cedula' => '0201234567', 'nombres' => 'Luis', 'apellidos' => 'Mora',
            'rol' => 'docente', 'facultad' => 'Ciencias Administrativas', 'carrera' => 'Software', 'telefono' => '0991234567',
        ]);
    }

    public function authorize(Usuario $user): bool
    {
        return $user->can('createTutoringTeacher', Usuario::class);
    }

    /** @param  array<string, string|null>  $row */
    protected function role(array $row): string
    {
        return self::assertRole($row, 'docente');
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

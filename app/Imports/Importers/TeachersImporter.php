<?php

namespace App\Imports\Importers;

use App\Models\Usuario;
use App\Support\TutoringCoordinatorAccess;

class TeachersImporter extends AccountImporter
{
    public function columns(): array
    {
        return ['correo' => true, 'carrera' => true, 'cedula' => false, 'nombre' => false, 'telefono' => false];
    }

    public function example(): array
    {
        return ['correo' => 'docente@ueb.edu.ec', 'carrera' => 'Software', 'cedula' => '', 'nombre' => '', 'telefono' => ''];
    }

    public function authorize(Usuario $user): bool
    {
        return $user->can('createTutoringTeacher', Usuario::class);
    }

    /** @param  array<string, string|null>  $row */
    protected function role(array $row): string
    {
        return 'docente';
    }

    /** @param  array<string, string|null>  $row */
    protected function careerId(array $row, Usuario $user, string $role): ?int
    {
        $careerId = $this->lookup->careerId($row['carrera']);
        app(TutoringCoordinatorAccess::class)->authorizeCareer($user, $careerId);

        return $careerId;
    }

    protected function extraRules(): array
    {
        return ['correo' => ['ends_with:@ueb.edu.ec']];
    }
}

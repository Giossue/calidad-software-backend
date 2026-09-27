<?php

namespace App\Policies;

use App\Models\Usuario;

class TemaTitulacionPolicy
{
    public function viewAny(Usuario $user): bool
    {
        return $user->estado && $user->hasAnyRole(['coordinador_titulacion', 'administrador']);
    }

    public function viewOwn(Usuario $user): bool
    {
        return $user->estado && $user->hasAnyRole(['estudiante', 'administrador']);
    }
}

<?php

namespace App\Policies;

use App\Models\Usuario;

class FacultadPolicy
{
    public function viewAny(Usuario $user): bool
    {
        return $user->rol === 'administrador' && $user->estado;
    }
}

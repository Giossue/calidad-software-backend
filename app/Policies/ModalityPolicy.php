<?php

namespace App\Policies;

use App\Models\Modalidad;
use App\Models\Usuario;

class ModalityPolicy
{
    public function viewAny(Usuario $user): bool
    {
        return $user->hasRole('administrador');
    }

    public function create(Usuario $user): bool
    {
        return $user->hasRole('administrador');
    }

    public function update(Usuario $user, Modalidad $modality): bool
    {
        return $user->hasRole('administrador');
    }

    public function deactivate(Usuario $user, Modalidad $modality): bool
    {
        return $user->hasRole('administrador');
    }

    public function activate(Usuario $user, Modalidad $modality): bool
    {
        return $user->hasRole('administrador');
    }
}

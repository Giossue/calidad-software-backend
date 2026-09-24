<?php

namespace App\Policies;

use App\Models\Paralelo;
use App\Models\Usuario;

class ParaleloPolicy
{
    public function viewAny(Usuario $user): bool
    {
        return $user->hasRole('administrador');
    }

    public function create(Usuario $user): bool
    {
        return $user->hasRole('administrador');
    }

    public function update(Usuario $user, Paralelo $paralelo): bool
    {
        return $user->hasRole('administrador');
    }

    public function deactivate(Usuario $user, Paralelo $paralelo): bool
    {
        return $user->hasRole('administrador');
    }

    public function activate(Usuario $user, Paralelo $paralelo): bool
    {
        return $user->hasRole('administrador');
    }
}

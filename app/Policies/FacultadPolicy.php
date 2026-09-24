<?php

namespace App\Policies;

use App\Models\Facultad;
use App\Models\Usuario;

class FacultadPolicy
{
    public function create(Usuario $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(Usuario $user, Facultad $faculty): bool
    {
        return $this->viewAny($user);
    }

    public function deactivate(Usuario $user, Facultad $faculty): bool
    {
        return $this->viewAny($user) && $faculty->estado;
    }

    public function activate(Usuario $user, Facultad $faculty): bool
    {
        return $this->viewAny($user) && ! $faculty->estado;
    }

    public function viewAny(Usuario $user): bool
    {
        return $user->hasRole('administrador') && $user->estado;
    }
}

<?php

namespace App\Policies;

use App\Models\Ciclo;
use App\Models\Usuario;

class CicloPolicy
{
    public function viewAny(Usuario $user): bool
    {
        return $this->create($user);
    }

    public function create(Usuario $user): bool
    {
        return $user->rol === 'administrador' && $user->estado;
    }

    public function update(Usuario $user, Ciclo $ciclo): bool
    {
        return $this->create($user);
    }

    public function deactivate(Usuario $user, Ciclo $ciclo): bool
    {
        return $this->create($user) && $ciclo->estado;
    }

    public function activate(Usuario $user, Ciclo $ciclo): bool
    {
        return $this->create($user) && ! $ciclo->estado;
    }
}

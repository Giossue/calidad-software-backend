<?php

namespace App\Policies;

use App\Models\Carrera;
use App\Models\Usuario;

class CarreraPolicy
{
    public function viewAny(Usuario $user): bool
    {
        return $this->create($user);
    }

    public function create(Usuario $user): bool
    {
        return $user->rol === 'administrador' && $user->estado;
    }

    public function update(Usuario $user, Carrera $carrera): bool
    {
        return $this->create($user);
    }

    public function deactivate(Usuario $user, Carrera $carrera): bool
    {
        return $this->create($user) && $carrera->estado;
    }

    public function activate(Usuario $user, Carrera $carrera): bool
    {
        return $this->create($user) && ! $carrera->estado;
    }
}

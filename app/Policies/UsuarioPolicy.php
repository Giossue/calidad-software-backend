<?php

namespace App\Policies;

use App\Models\Usuario;

class UsuarioPolicy
{
    public function viewAny(Usuario $user): bool
    {
        return $user->rol === 'administrador' && $user->estado;
    }

    public function create(Usuario $user): bool
    {
        return $user->rol === 'administrador' && $user->estado;
    }

    public function update(Usuario $user, Usuario $target): bool
    {
        return $this->create($user);
    }

    public function deactivate(Usuario $user, Usuario $target): bool
    {
        return $this->create($user) && $target->estado;
    }

    public function activate(Usuario $user, Usuario $target): bool
    {
        return $this->create($user) && ! $target->estado;
    }
}

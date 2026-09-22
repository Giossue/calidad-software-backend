<?php

namespace App\Policies;

use App\Models\Modalidad;
use App\Models\Usuario;

class ModalityPolicy
{
    public function viewAny(Usuario $user): bool
    {
        return $user->rol === 'administrador';
    }

    public function create(Usuario $user): bool
    {
        return $user->rol === 'administrador';
    }

    public function update(Usuario $user, Modalidad $modality): bool
    {
        return $user->rol === 'administrador';
    }

    public function deactivate(Usuario $user, Modalidad $modality): bool
    {
        return $user->rol === 'administrador';
    }
}

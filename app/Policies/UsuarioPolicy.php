<?php

namespace App\Policies;

use App\Models\Usuario;
use App\Support\TutoringCoordinatorAccess;

class UsuarioPolicy
{
    public function viewTutoringTeachers(Usuario $user): bool
    {
        return app(TutoringCoordinatorAccess::class)->allowed($user);
    }

    public function createTutoringTeacher(Usuario $user): bool
    {
        return $this->viewTutoringTeachers($user);
    }

    public function updateTutoringTeacher(Usuario $user, Usuario $teacher): bool
    {
        return app(TutoringCoordinatorAccess::class)->allowsTeacher($user, $teacher);
    }

    public function viewAny(Usuario $user): bool
    {
        return $user->hasRole('administrador') && $user->estado;
    }

    public function create(Usuario $user): bool
    {
        return $user->hasRole('administrador') && $user->estado;
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

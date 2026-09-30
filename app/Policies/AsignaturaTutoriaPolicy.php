<?php

namespace App\Policies;

use App\Models\AsignaturaTutoria;
use App\Models\Usuario;
use App\Support\TutoringCoordinatorAccess;

class AsignaturaTutoriaPolicy
{
    public function viewAssignedAny(Usuario $user): bool
    {
        return $user->estado && $user->hasRole('docente');
    }

    public function viewAssigned(Usuario $user, AsignaturaTutoria $tutoring): bool
    {
        return $this->viewAssignedAny($user) && $tutoring->fk_docente === $user->getKey();
    }

    public function teach(Usuario $user, AsignaturaTutoria $tutoring): bool
    {
        return $this->viewAssigned($user, $tutoring) && $tutoring->estado && $tutoring->periodo->estado;
    }

    public function viewAny(Usuario $user): bool
    {
        return app(TutoringCoordinatorAccess::class)->allowed($user);
    }

    public function create(Usuario $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(Usuario $user, AsignaturaTutoria $tutoring): bool
    {
        return app(TutoringCoordinatorAccess::class)->allowsCareer($user, $tutoring->ciclo->fk_carrera);
    }

    public function update(Usuario $user, AsignaturaTutoria $tutoring): bool
    {
        return $this->view($user, $tutoring);
    }
}

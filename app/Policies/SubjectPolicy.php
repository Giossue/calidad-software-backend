<?php

namespace App\Policies;

use App\Models\Subject;
use App\Models\Usuario;
use App\Support\TutoringCoordinatorAccess;

class SubjectPolicy
{
    public function viewAny(Usuario $user): bool
    {
        return app(TutoringCoordinatorAccess::class)->allowed($user);
    }

    public function create(Usuario $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(Usuario $user, Subject $subject): bool
    {
        return app(TutoringCoordinatorAccess::class)->allowsCareer($user, $subject->career_id);
    }

    public function update(Usuario $user, Subject $subject): bool
    {
        return $this->view($user, $subject);
    }
}

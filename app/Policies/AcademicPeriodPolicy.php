<?php

namespace App\Policies;

use App\Models\PeriodoAcademico;
use App\Models\Usuario;

class AcademicPeriodPolicy
{
    public function viewAny(Usuario $user): bool
    {
        return $user->rol === 'administrador';
    }

    public function create(Usuario $user): bool
    {
        return $user->rol === 'administrador';
    }

    public function update(Usuario $user, PeriodoAcademico $academicPeriod): bool
    {
        return $user->rol === 'administrador';
    }

    public function deactivate(Usuario $user, PeriodoAcademico $academicPeriod): bool
    {
        return $user->rol === 'administrador';
    }

    public function activate(Usuario $user, PeriodoAcademico $academicPeriod): bool
    {
        return $user->rol === 'administrador';
    }
}

<?php

namespace App\Support;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;

class TutoringCoordinatorAccess
{
    public function allowed(Usuario $user): bool
    {
        return $user->estado && $user->hasAnyRole(['coordinador_carrera', 'administrador']);
    }

    public function allowsCareer(Usuario $user, int $careerId): bool
    {
        return $this->allowed($user) && (
            $user->hasRole('administrador')
            || $user->coordinatedCareers()->whereKey($careerId)->exists()
        );
    }

    public function authorizeCareer(Usuario $user, int $careerId): void
    {
        abort_unless($this->allowsCareer($user, $careerId), Response::HTTP_FORBIDDEN);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeCareer(Builder $query, Usuario $user, string $column = 'fk_carrera'): Builder
    {
        if ($user->hasRole('administrador')) {
            return $query;
        }

        return $query->whereIn($column, $user->coordinatedCareers()->select('carrera.id_carrera'));
    }

    public function allowsTeacher(Usuario $user, Usuario $teacher): bool
    {
        if (! $this->allowed($user) || ! $teacher->hasRole('docente')) {
            return false;
        }

        if ($user->hasRole('administrador')) {
            return true;
        }

        $careerIds = $user->coordinatedCareers()->pluck('carrera.id_carrera');

        return $teacher->roleSlugs()->count() === 1
            && $teacher->teachingCareers()->exists()
            && ! $teacher->teachingCareers()->whereNotIn('carrera.id_carrera', $careerIds)->exists()
            && ! $teacher->asignaturasTutoria()->whereHas('ciclo', fn (Builder $query) => $query->whereNotIn('fk_carrera', $careerIds))->exists();
    }
}

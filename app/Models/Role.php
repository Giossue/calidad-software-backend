<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 */
#[Fillable(['slug', 'name'])]
class Role extends Model
{
    /**
     * @return BelongsToMany<Usuario, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(Usuario::class, 'role_user', 'role_id', 'user_id', 'id', 'id_usuario')
            ->withPivot('assigned_at');
    }
}

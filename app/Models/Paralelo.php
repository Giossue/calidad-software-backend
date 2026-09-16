<?php

// ============================================================
// app/Models/Paralelo.php
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Paralelo extends Model
{
    protected $table = 'paralelo';

    protected $primaryKey = 'id_paralelo';

    protected $fillable = ['nombre', 'estado'];

    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }

    /**
     * @return BelongsToMany<Usuario, $this>
     */
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(
            Usuario::class,
            'usuario_paralelo',
            'fk_paralelo',
            'fk_usuario'
        )->withPivot('fecha_asignacion', 'estado')->withTimestamps();
    }
}

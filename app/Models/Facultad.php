<?php

// ============================================================
// app/Models/Facultad.php
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facultad extends Model
{
    protected $table = 'facultad';

    protected $primaryKey = 'id_facultad';

    protected $fillable = ['nombre', 'estado'];

    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }

    /**
     * @return HasMany<Carrera, $this>
     */
    public function careers(): HasMany
    {
        return $this->hasMany(Carrera::class, 'fk_facultad');
    }
}

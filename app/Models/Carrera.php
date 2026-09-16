<?php

// ============================================================
// app/Models/Carrera.php
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Carrera extends Model
{
    protected $table = 'carrera';

    protected $primaryKey = 'id_carrera';

    protected $fillable = ['fk_facultad', 'nombre', 'estado'];

    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }

    /**
     * @return BelongsTo<Facultad, $this>
     */
    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Facultad::class, 'fk_facultad');
    }

    /**
     * @return HasMany<Ciclo, $this>
     */
    public function cycles(): HasMany
    {
        return $this->hasMany(Ciclo::class, 'fk_carrera');
    }
}

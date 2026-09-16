<?php

// ============================================================
// app/Models/Tema.php
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tema extends Model
{
    protected $table = 'tema';

    protected $primaryKey = 'id_tema';

    protected $fillable = [
        'fk_asig_tutoria', 'nombre',
        'descripcion', 'visto', 'estado',
    ];

    protected function casts(): array
    {
        return ['visto' => 'boolean', 'estado' => 'boolean'];
    }

    /**
     * @return BelongsTo<AsignaturaTutoria, $this>
     */
    public function tutoringSubject(): BelongsTo
    {
        return $this->belongsTo(AsignaturaTutoria::class, 'fk_asig_tutoria');
    }

    /**
     * @return HasMany<Actividad, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Actividad::class, 'fk_tema');
    }
}

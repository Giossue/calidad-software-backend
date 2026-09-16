<?php

// ============================================================
// app/Models/MetricaConocimiento.php
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetricaConocimiento extends Model
{
    protected $table = 'metrica_conocimiento';

    protected $primaryKey = 'id_metrica';

    protected $fillable = [
        'fk_id_usuario', 'descripcion', 'rango',
        'nota_minima', 'nota_maxima', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'nota_minima' => 'decimal:2',
            'nota_maxima' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'fk_id_usuario');
    }

    /**
     * @return HasMany<PlanAccion, $this>
     */
    public function planesAccion(): HasMany
    {
        return $this->hasMany(PlanAccion::class, 'fk_metrica');
    }
}

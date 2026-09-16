<?php

// ============================================================
// app/Models/FichaSeguimiento.php
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FichaSeguimiento extends Model
{
    protected $table = 'ficha_seguimiento';

    protected $primaryKey = 'id_ficha';

    protected $fillable = [
        'fk_tema_tit', 'fecha_apertura',
        'porcentaje_avance', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_apertura' => 'date',
            'porcentaje_avance' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<TemaTitulacion, $this>
     */
    public function degreeTopic(): BelongsTo
    {
        return $this->belongsTo(TemaTitulacion::class, 'fk_tema_tit');
    }

    /**
     * @return HasMany<ActividadAvance, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(ActividadAvance::class, 'fk_ficha');
    }

    /**
     * @return HasMany<InformeTitulacion, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(InformeTitulacion::class, 'fk_ficha');
    }
}

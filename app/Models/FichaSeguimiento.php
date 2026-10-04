<?php

// ============================================================
// app/Models/FichaSeguimiento.php
// ============================================================

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonInterface|null $fecha_apertura
 * @property-read TemaTitulacion|null $temaTitulacion
 * @property-read Collection<int, ActividadAvance> $actividades
 * @property-read Collection<int, InformeTitulacion> $informes
 */
class FichaSeguimiento extends Model
{
    protected $table = 'ficha_seguimiento';

    protected $primaryKey = 'id_ficha';

    protected $fillable = [
        'fk_tema_tit', 'fecha_apertura',
        'porcentaje_avance', 'estado',
    ];

    /** @return array<string, string> */
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
    public function temaTitulacion(): BelongsTo
    {
        return $this->belongsTo(TemaTitulacion::class, 'fk_tema_tit');
    }

    /**
     * @return HasMany<ActividadAvance, $this>
     */
    public function actividades(): HasMany
    {
        return $this->hasMany(ActividadAvance::class, 'fk_ficha');
    }

    /**
     * @return HasMany<InformeTitulacion, $this>
     */
    public function informes(): HasMany
    {
        return $this->hasMany(InformeTitulacion::class, 'fk_ficha');
    }
}

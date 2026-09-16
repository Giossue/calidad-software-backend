<?php

// ============================================================
// app/Models/TemaTitulacion.php
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TemaTitulacion extends Model
{
    protected $table = 'tema_titulacion';

    protected $primaryKey = 'id_tema_tit';

    protected $fillable = [
        'fk_id_usuario', 'fk_periodo', 'fk_coord_revisor',
        'titulo', 'descripcion', 'estado',
        'fecha_propuesta', 'fecha_revision',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
            'fecha_propuesta' => 'date',
            'fecha_revision' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'fk_id_usuario');
    }

    /**
     * @return BelongsTo<PeriodoAcademico, $this>
     */
    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoAcademico::class, 'fk_periodo');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function coordinadorRevisor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'fk_coord_revisor');
    }

    /**
     * @return HasMany<AsignacionDocente, $this>
     */
    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionDocente::class, 'fk_tema_tit');
    }

    /**
     * @return HasOne<FichaSeguimiento, $this>
     */
    public function fichaSeguimiento(): HasOne
    {
        return $this->hasOne(FichaSeguimiento::class, 'fk_tema_tit');
    }

    /**
     * @return HasMany<HorarioTitulacion, $this>
     */
    public function horarios(): HasMany
    {
        return $this->hasMany(HorarioTitulacion::class, 'fk_tema_tit');
    }

    /**
     * @return HasMany<ObservacionTitulacion, $this>
     */
    public function observaciones(): HasMany
    {
        return $this->hasMany(ObservacionTitulacion::class, 'fk_tema_tit');
    }
}

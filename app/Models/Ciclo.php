<?php

// ============================================================
// app/Models/Ciclo.php
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ciclo extends Model
{
    protected $table = 'ciclo';

    protected $primaryKey = 'id_ciclo';

    protected $fillable = ['fk_carrera', 'nombre', 'numero', 'estado'];

    protected function casts(): array
    {
        return ['estado' => 'boolean', 'numero' => 'integer'];
    }

    /**
     * @return BelongsTo<Carrera, $this>
     */
    public function career(): BelongsTo
    {
        return $this->belongsTo(Carrera::class, 'fk_carrera');
    }

    /**
     * @return BelongsToMany<PeriodoAcademico, $this>
     */
    public function academicPeriods(): BelongsToMany
    {
        return $this->belongsToMany(
            PeriodoAcademico::class,
            'ciclo_periodo',
            'fk_ciclo',
            'fk_periodo'
        )->withPivot('estado')->withTimestamps();
    }

    /**
     * @return HasMany<AsignaturaTutoria, $this>
     */
    public function tutoringSubjects(): HasMany
    {
        return $this->hasMany(AsignaturaTutoria::class, 'fk_ciclo');
    }
}

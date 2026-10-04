<?php

// ============================================================
// app/Models/Ciclo.php
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property-read Carrera|null $carrera */
class Ciclo extends Model
{
    protected $table = 'ciclo';

    protected $primaryKey = 'id_ciclo';

    protected $fillable = ['fk_carrera', 'nombre', 'numero', 'fk_paralelo', 'estado'];

    protected function casts(): array
    {
        return ['estado' => 'boolean', 'numero' => 'integer'];
    }

    /**
     * @return BelongsTo<Carrera, $this>
     */
    public function carrera(): BelongsTo
    {
        return $this->belongsTo(Carrera::class, 'fk_carrera');
    }

    /**
     * @return BelongsTo<Paralelo, $this>
     */
    public function paralelo(): BelongsTo
    {
        return $this->belongsTo(Paralelo::class, 'fk_paralelo');
    }

    /**
     * @return BelongsToMany<PeriodoAcademico, $this>
     */
    public function periodos(): BelongsToMany
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
    public function asignaturasTutoria(): HasMany
    {
        return $this->hasMany(AsignaturaTutoria::class, 'fk_ciclo');
    }

    /** @return BelongsToMany<Subject, $this> */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'subject_cycle', 'cycle_id', 'subject_id')
            ->withTimestamps();
    }
}

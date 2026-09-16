<?php

// ============================================================
// app/Models/CicloPeriodo.php
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CicloPeriodo extends Model
{
    protected $table = 'ciclo_periodo';

    protected $primaryKey = null;

    public $incrementing = false;

    protected $fillable = ['fk_ciclo', 'fk_periodo', 'estado'];

    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }

    /**
     * Scope updates and deletes by both columns of the composite primary key.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function setKeysForSelectQuery($query)
    {
        return $query
            ->where('fk_ciclo', $this->getOriginal('fk_ciclo', $this->getAttribute('fk_ciclo')))
            ->where('fk_periodo', $this->getOriginal('fk_periodo', $this->getAttribute('fk_periodo')));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function setKeysForSaveQuery($query)
    {
        return $this->setKeysForSelectQuery($query);
    }

    /**
     * @return BelongsTo<Ciclo, $this>
     */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Ciclo::class, 'fk_ciclo');
    }

    /**
     * @return BelongsTo<PeriodoAcademico, $this>
     */
    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(PeriodoAcademico::class, 'fk_periodo');
    }
}

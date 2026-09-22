<?php

// ============================================================
// app/Models/PeriodoAcademico.php
// ============================================================

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id_periodo
 * @property string $nombre
 * @property CarbonInterface $fecha_inicio
 * @property CarbonInterface $fecha_fin
 * @property bool $estado
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class PeriodoAcademico extends Model
{
    protected $table = 'periodo_academico';

    protected $primaryKey = 'id_periodo';

    protected $fillable = ['nombre', 'fecha_inicio', 'fecha_fin', 'estado'];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
        ];
    }

    /**
     * @return BelongsToMany<Ciclo, $this>
     */
    public function ciclos(): BelongsToMany
    {
        return $this->belongsToMany(
            Ciclo::class,
            'ciclo_periodo',
            'fk_periodo',
            'fk_ciclo'
        )->withPivot('estado')->withTimestamps();
    }

    /**
     * @return HasMany<AsignaturaTutoria, $this>
     */
    public function asignaturasTutoria(): HasMany
    {
        return $this->hasMany(AsignaturaTutoria::class, 'fk_periodo');
    }
}

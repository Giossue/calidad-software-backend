<?php

// ============================================================
// app/Models/InscripcionTutoria.php
// ============================================================

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property CarbonInterface $fecha_inscripcion
 * @property-read AsignaturaTutoria|null $asignaturaTutoria
 */
class InscripcionTutoria extends Model
{
    protected $table = 'inscripcion_tutoria';

    protected $primaryKey = 'id_inscripcion';

    protected $fillable = [
        'fk_asig_tutoria', 'fk_id_usuario',
        'fecha_inscripcion', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
            'fecha_inscripcion' => 'date',
        ];
    }

    /**
     * @return BelongsTo<AsignaturaTutoria, $this>
     */
    public function asignaturaTutoria(): BelongsTo
    {
        return $this->belongsTo(AsignaturaTutoria::class, 'fk_asig_tutoria');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'fk_id_usuario');
    }

    /**
     * @return HasMany<Nota, $this>
     */
    public function notas(): HasMany
    {
        return $this->hasMany(Nota::class, 'fk_inscripcion');
    }

    /**
     * @return HasMany<Asistencia, $this>
     */
    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class, 'fk_inscripcion');
    }

    /** @return HasOne<MetricaConocimiento, $this> */
    public function knowledgeMetric(): HasOne
    {
        return $this->hasOne(MetricaConocimiento::class, 'enrollment_id');
    }
}

<?php

// ============================================================
// app/Models/AsignaturaTutoria.php
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AsignaturaTutoria extends Model
{
    protected $table = 'asignatura_tutoria';

    protected $primaryKey = 'id_asig_tutoria';

    protected $fillable = [
        'fk_ciclo', 'fk_periodo', 'fk_modalidad',
        'fk_paralelo', 'fk_docente', 'nombre', 'estado',
    ];

    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }

    /**
     * @return BelongsTo<Ciclo, $this>
     */
    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(Ciclo::class, 'fk_ciclo');
    }

    /**
     * @return BelongsTo<PeriodoAcademico, $this>
     */
    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoAcademico::class, 'fk_periodo');
    }

    /**
     * @return BelongsTo<Modalidad, $this>
     */
    public function modalidad(): BelongsTo
    {
        return $this->belongsTo(Modalidad::class, 'fk_modalidad');
    }

    /**
     * @return BelongsTo<Paralelo, $this>
     */
    public function paralelo(): BelongsTo
    {
        return $this->belongsTo(Paralelo::class, 'fk_paralelo');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function docente(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'fk_docente');
    }

    /**
     * @return HasMany<Horario, $this>
     */
    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class, 'fk_asig_tutoria');
    }

    /**
     * @return HasMany<Tema, $this>
     */
    public function temas(): HasMany
    {
        return $this->hasMany(Tema::class, 'fk_asig_tutoria');
    }

    /**
     * @return HasMany<InscripcionTutoria, $this>
     */
    public function inscripciones(): HasMany
    {
        return $this->hasMany(InscripcionTutoria::class, 'fk_asig_tutoria');
    }

    /**
     * @return HasMany<Observacion, $this>
     */
    public function observaciones(): HasMany
    {
        return $this->hasMany(Observacion::class, 'fk_asig_tutoria');
    }

    /**
     * @return HasMany<Reporte, $this>
     */
    public function reportes(): HasMany
    {
        return $this->hasMany(Reporte::class, 'fk_asig_tutoria');
    }
}

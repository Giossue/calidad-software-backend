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

    /**
     * @return BelongsTo<Modalidad, $this>
     */
    public function modality(): BelongsTo
    {
        return $this->belongsTo(Modalidad::class, 'fk_modalidad');
    }

    /**
     * @return BelongsTo<Paralelo, $this>
     */
    public function parallel(): BelongsTo
    {
        return $this->belongsTo(Paralelo::class, 'fk_paralelo');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fk_docente');
    }

    /**
     * @return HasMany<Horario, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Horario::class, 'fk_asig_tutoria');
    }

    /**
     * @return HasMany<Tema, $this>
     */
    public function topics(): HasMany
    {
        return $this->hasMany(Tema::class, 'fk_asig_tutoria');
    }

    /**
     * @return HasMany<InscripcionTutoria, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(InscripcionTutoria::class, 'fk_asig_tutoria');
    }

    /**
     * @return HasMany<Observacion, $this>
     */
    public function observations(): HasMany
    {
        return $this->hasMany(Observacion::class, 'fk_asig_tutoria');
    }

    /**
     * @return HasMany<Reporte, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Reporte::class, 'fk_asig_tutoria');
    }
}

<?php

// ============================================================
// app/Models/InscripcionTutoria.php
// ============================================================

namespace App\Models;

use App\Observers\TutoringEnrollmentObserver;
use App\Support\StudentCycle;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property CarbonInterface $fecha_inscripcion
 * @property string|null $final_result `approved` o `failed`, registrado por el docente al cerrar el ciclo.
 * @property CarbonInterface|null $final_result_at
 * @property CarbonInterface|null $abandoned_at Cuándo se registró que el estudiante abandonó la tutoría; null si no la abandonó.
 * @property string|null $abandonment_reason Motivo del abandono, en texto libre.
 * @property-read AsignaturaTutoria|null $asignaturaTutoria
 */
#[ObservedBy([TutoringEnrollmentObserver::class])]
class InscripcionTutoria extends Model
{
    protected $table = 'inscripcion_tutoria';

    protected $primaryKey = 'id_inscripcion';

    public const APPROVED = 'approved';

    public const FAILED = 'failed';

    protected $fillable = [
        'fk_asig_tutoria', 'fk_id_usuario',
        'fecha_inscripcion', 'estado', 'final_result', 'final_result_at',
        'abandoned_at', 'abandonment_reason',
    ];

    /**
     * Cada alta, rehabilitación o baja de una inscripción recalcula el ciclo del
     * estudiante en el período de la tutoría, venga de coordinación, del docente,
     * del avance automático o de una importación.
     */
    protected static function booted(): void
    {
        static::saved(function (InscripcionTutoria $enrollment): void {
            if ($enrollment->wasRecentlyCreated || $enrollment->wasChanged(['estado', 'fk_asig_tutoria', 'fk_id_usuario'])) {
                app(StudentCycle::class)->syncEnrollment($enrollment);
            }
        });
        static::deleted(fn (InscripcionTutoria $enrollment) => app(StudentCycle::class)->syncEnrollment($enrollment));
    }

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
            'fecha_inscripcion' => 'date',
            'final_result_at' => 'datetime',
            'abandoned_at' => 'datetime',
        ];
    }

    public function isAbandoned(): bool
    {
        return $this->abandoned_at !== null;
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

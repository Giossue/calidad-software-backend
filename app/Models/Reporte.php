<?php

// ============================================================
// app/Models/Reporte.php
// ============================================================

namespace App\Models;

use App\Actions\Teacher\DiagnosticEvaluationReport;
use App\Actions\Teacher\DiagnosticGradesReport;
use App\Actions\Teacher\FinalPaoReport;
use App\Actions\Teacher\TutoringFollowUpReport;
use App\Observers\TutoringReportObserver;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string|null $content
 * @property CarbonInterface $fecha_generacion
 * @property array<string, mixed>|null $summary
 */
#[ObservedBy([TutoringReportObserver::class])]
class Reporte extends Model
{
    protected $table = 'reporte';

    protected $primaryKey = 'id_reporte';

    protected $fillable = [
        'fk_asig_tutoria', 'tipo_reporte',
        'fk_id_usuario', 'fecha_generacion', 'content', 'summary',
    ];

    protected function casts(): array
    {
        return ['fecha_generacion' => 'datetime', 'summary' => 'array'];
    }

    /** Formatos oficiales de Tutorías Académicas que el docente envía a coordinación. */
    public const OFFICIAL_FORMATS = [
        DiagnosticEvaluationReport::FORMAT,
        DiagnosticGradesReport::FORMAT,
        TutoringFollowUpReport::FORMAT,
        FinalPaoReport::FORMAT,
    ];

    /**
     * Solo los informes con formato oficial; deja fuera los informes libres.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function official(Builder $query): void
    {
        $query->whereIn('summary->format', self::OFFICIAL_FORMATS);
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
    public function generadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'fk_id_usuario');
    }
}

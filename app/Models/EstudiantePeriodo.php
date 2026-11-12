<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ciclo que cursó un estudiante en un período académico. Lo mantiene
 * `StudentCycle::syncPeriod` a partir de sus inscripciones activas del período.
 *
 * @property int $id_estudiante_periodo
 * @property int $fk_usuario
 * @property int $fk_periodo
 * @property int $fk_ciclo
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read PeriodoAcademico|null $periodo
 * @property-read Ciclo|null $ciclo
 */
class EstudiantePeriodo extends Model
{
    protected $table = 'estudiante_periodo';

    protected $primaryKey = 'id_estudiante_periodo';

    protected $fillable = ['fk_usuario', 'fk_periodo', 'fk_ciclo'];

    /** @return BelongsTo<Usuario, $this> */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'fk_usuario');
    }

    /** @return BelongsTo<PeriodoAcademico, $this> */
    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoAcademico::class, 'fk_periodo');
    }

    /** @return BelongsTo<Ciclo, $this> */
    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(Ciclo::class, 'fk_ciclo');
    }
}

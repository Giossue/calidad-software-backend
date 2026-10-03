<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id_matricula_tit
 * @property int $fk_estudiante
 * @property int $fk_periodo
 * @property int|null $fk_carrera
 * @property CarbonInterface $fecha_matricula
 * @property bool $estado
 * @property-read Usuario|null $estudiante
 * @property-read PeriodoAcademico|null $periodo
 * @property-read Carrera|null $carrera
 */
class MatriculaTitulacion extends Model
{
    protected $table = 'matricula_titulacion';

    protected $primaryKey = 'id_matricula_tit';

    protected $fillable = [
        'fk_estudiante',
        'fk_periodo',
        'fk_carrera',
        'fecha_matricula',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_matricula' => 'date',
            'estado' => 'boolean',
        ];
    }

    /** @return BelongsTo<Usuario, $this> */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'fk_estudiante');
    }

    /** @return BelongsTo<PeriodoAcademico, $this> */
    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoAcademico::class, 'fk_periodo');
    }

    /** @return BelongsTo<Carrera, $this> */
    public function carrera(): BelongsTo
    {
        return $this->belongsTo(Carrera::class, 'fk_carrera');
    }
}

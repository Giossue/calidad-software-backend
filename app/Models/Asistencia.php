<?php

// ============================================================
// app/Models/Asistencia.php
// ============================================================

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonInterface $fecha
 * @property-read Usuario|null $estudiante
 */
class Asistencia extends Model
{
    protected $table = 'asistencia';

    protected $primaryKey = 'id_asistencia';

    protected $fillable = [
        'fk_inscripcion', 'fk_id_usuario',
        'fecha', 'estado_asistencia', 'session_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'estado_asistencia' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<InscripcionTutoria, $this>
     */
    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(InscripcionTutoria::class, 'fk_inscripcion');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'fk_id_usuario');
    }

    /** @return BelongsTo<TutoringSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(TutoringSession::class, 'session_id');
    }
}

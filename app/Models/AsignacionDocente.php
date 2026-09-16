<?php

// ============================================================
// app/Models/AsignacionDocente.php
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsignacionDocente extends Model
{
    protected $table = 'asignacion_docente';

    protected $primaryKey = 'id_asignacion';

    protected $fillable = [
        'fk_tema_tit', 'fk_id_usuario',
        'rol', 'fecha_asignacion', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
            'fecha_asignacion' => 'date',
        ];
    }

    /**
     * @return BelongsTo<TemaTitulacion, $this>
     */
    public function temaTitulacion(): BelongsTo
    {
        return $this->belongsTo(TemaTitulacion::class, 'fk_tema_tit');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function docente(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'fk_id_usuario');
    }
}

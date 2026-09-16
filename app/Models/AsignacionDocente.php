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
    public function degreeTopic(): BelongsTo
    {
        return $this->belongsTo(TemaTitulacion::class, 'fk_tema_tit');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fk_id_usuario');
    }
}

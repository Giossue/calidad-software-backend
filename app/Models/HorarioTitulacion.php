<?php

// ============================================================
// app/Models/HorarioTitulacion.php
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HorarioTitulacion extends Model
{
    protected $table = 'horario_titulacion';

    protected $primaryKey = 'id_horario_tit';

    protected $fillable = [
        'fk_tema_tit', 'fk_coord_tit', 'dia_semana',
        'hora_inicio', 'hora_fin', 'modalidad', 'estado',
    ];

    protected function casts(): array
    {
        return ['estado' => 'boolean'];
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
    public function coordinador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'fk_coord_tit');
    }
}

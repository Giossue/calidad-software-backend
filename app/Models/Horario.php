<?php

// ============================================================
// app/Models/Horario.php
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Horario extends Model
{
    protected $table = 'horario';

    protected $primaryKey = 'id_horario';

    protected $fillable = [
        'fk_asig_tutoria', 'dia_semana',
        'hora_inicio', 'hora_fin', 'estado',
    ];

    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }

    /**
     * @return BelongsTo<AsignaturaTutoria, $this>
     */
    public function tutoringSubject(): BelongsTo
    {
        return $this->belongsTo(AsignaturaTutoria::class, 'fk_asig_tutoria');
    }
}

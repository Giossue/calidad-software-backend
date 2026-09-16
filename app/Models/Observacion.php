<?php

// ============================================================
// app/Models/Observacion.php
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Observacion extends Model
{
    protected $table = 'observacion';

    protected $primaryKey = 'id_observacion';

    protected $fillable = [
        'fk_asig_tutoria', 'fk_docente',
        'descripcion', 'fecha_registro',
    ];

    protected function casts(): array
    {
        return ['fecha_registro' => 'date'];
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
    public function docente(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'fk_docente');
    }
}

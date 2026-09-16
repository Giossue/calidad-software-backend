<?php

// ============================================================
// app/Models/Reporte.php
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reporte extends Model
{
    protected $table = 'reporte';

    protected $primaryKey = 'id_reporte';

    protected $fillable = [
        'fk_asig_tutoria', 'tipo_reporte',
        'fk_id_usuario', 'fecha_generacion',
    ];

    protected function casts(): array
    {
        return ['fecha_generacion' => 'datetime'];
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

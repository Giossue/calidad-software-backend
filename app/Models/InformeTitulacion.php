<?php

// ============================================================
// app/Models/InformeTitulacion.php
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InformeTitulacion extends Model
{
    protected $table = 'informe_titulacion';

    protected $primaryKey = 'id_informe';

    protected $fillable = [
        'fk_ficha', 'fk_coord_tit',
        'fecha_generacion', 'observaciones_finales', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
            'fecha_generacion' => 'date',
        ];
    }

    /**
     * @return BelongsTo<FichaSeguimiento, $this>
     */
    public function trackingSheet(): BelongsTo
    {
        return $this->belongsTo(FichaSeguimiento::class, 'fk_ficha');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fk_coord_tit');
    }
}

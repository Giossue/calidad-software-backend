<?php

// ============================================================
// app/Models/ObservacionTitulacion.php
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObservacionTitulacion extends Model
{
    protected $table = 'observacion_titulacion';

    protected $primaryKey = 'id_obs_tit';

    protected $fillable = [
        'fk_tema_tit', 'fk_coord_tit',
        'descripcion', 'fecha_registro',
    ];

    protected function casts(): array
    {
        return ['fecha_registro' => 'date'];
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
    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fk_coord_tit');
    }
}

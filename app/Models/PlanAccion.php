<?php

// ============================================================
// app/Models/PlanAccion.php
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanAccion extends Model
{
    protected $table = 'plan_accion';

    protected $primaryKey = 'id_plan';

    protected $fillable = ['fk_metrica', 'descripcion', 'estado'];

    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }

    /**
     * @return BelongsTo<MetricaConocimiento, $this>
     */
    public function metrica(): BelongsTo
    {
        return $this->belongsTo(MetricaConocimiento::class, 'fk_metrica');
    }
}

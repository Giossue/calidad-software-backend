<?php

// ============================================================
// app/Models/Metodologia.php
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Metodologia extends Model
{
    protected $table = 'metodologia';

    protected $primaryKey = 'id_metodologia';

    protected $fillable = ['fk_actividad', 'descripcion', 'estado'];

    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }

    /**
     * @return BelongsTo<Actividad, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Actividad::class, 'fk_actividad');
    }
}

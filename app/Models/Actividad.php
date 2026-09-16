<?php

// ============================================================
// app/Models/Actividad.php
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Actividad extends Model
{
    protected $table = 'actividad';

    protected $primaryKey = 'id_actividad';

    protected $fillable = ['fk_tema', 'nombre', 'duracion', 'estado'];

    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }

    /**
     * @return BelongsTo<Tema, $this>
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(Tema::class, 'fk_tema');
    }

    /**
     * @return HasMany<Metodologia, $this>
     */
    public function methodologies(): HasMany
    {
        return $this->hasMany(Metodologia::class, 'fk_actividad');
    }
}

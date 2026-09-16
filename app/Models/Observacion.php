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
    public function tutoringSubject(): BelongsTo
    {
        return $this->belongsTo(AsignaturaTutoria::class, 'fk_asig_tutoria');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fk_docente');
    }
}

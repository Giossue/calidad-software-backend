<?php

// ============================================================
// app/Models/Asistencia.php
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asistencia extends Model
{
    protected $table = 'asistencia';

    protected $primaryKey = 'id_asistencia';

    protected $fillable = [
        'fk_inscripcion', 'fk_id_usuario',
        'fecha', 'estado_asistencia',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'estado_asistencia' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<InscripcionTutoria, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(InscripcionTutoria::class, 'fk_inscripcion');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fk_id_usuario');
    }
}

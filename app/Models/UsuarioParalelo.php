<?php

// ============================================================
// app/Models/UsuarioParalelo.php
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsuarioParalelo extends Model
{
    protected $table = 'usuario_paralelo';

    protected $primaryKey = 'id_usr_paralelo';

    protected $fillable = [
        'fk_usuario', 'fk_paralelo',
        'fecha_asignacion', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
            'fecha_asignacion' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'fk_usuario');
    }

    /**
     * @return BelongsTo<Paralelo, $this>
     */
    public function paralelo(): BelongsTo
    {
        return $this->belongsTo(Paralelo::class, 'fk_paralelo');
    }
}

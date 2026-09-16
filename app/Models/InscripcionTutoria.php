<?php

// ============================================================
// app/Models/InscripcionTutoria.php
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InscripcionTutoria extends Model
{
    protected $table = 'inscripcion_tutoria';

    protected $primaryKey = 'id_inscripcion';

    protected $fillable = [
        'fk_asig_tutoria', 'fk_id_usuario',
        'fecha_inscripcion', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
            'fecha_inscripcion' => 'date',
        ];
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
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fk_id_usuario');
    }

    /**
     * @return HasMany<Nota, $this>
     */
    public function grades(): HasMany
    {
        return $this->hasMany(Nota::class, 'fk_inscripcion');
    }

    /**
     * @return HasMany<Asistencia, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Asistencia::class, 'fk_inscripcion');
    }
}

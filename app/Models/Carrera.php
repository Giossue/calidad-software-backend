<?php

// ============================================================
// app/Models/Carrera.php
// ============================================================

namespace App\Models;

use App\Support\Mallas;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Carrera extends Model
{
    protected $table = 'carrera';

    protected $primaryKey = 'id_carrera';

    protected $fillable = ['fk_facultad', 'fk_modalidad', 'nombre', 'codigo', 'estado'];

    protected static function booted(): void
    {
        // Toda carrera nueva nace con su malla vigente «Malla inicial».
        static::created(fn (Carrera $career) => Mallas::ensureInitial((int) $career->getKey()));
    }

    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }

    /**
     * @return BelongsTo<Facultad, $this>
     */
    public function facultad(): BelongsTo
    {
        return $this->belongsTo(Facultad::class, 'fk_facultad');
    }

    /**
     * @return BelongsTo<Modalidad, $this>
     */
    public function modalidad(): BelongsTo
    {
        return $this->belongsTo(Modalidad::class, 'fk_modalidad');
    }

    /**
     * @return HasMany<Ciclo, $this>
     */
    public function ciclos(): HasMany
    {
        return $this->hasMany(Ciclo::class, 'fk_carrera');
    }

    /**
     * Mallas de la carrera: la vigente primero y luego por nombre.
     *
     * @return HasMany<Malla, $this>
     */
    public function mallas(): HasMany
    {
        return $this->hasMany(Malla::class, 'career_id')->orderByDesc('is_current')->orderBy('name');
    }

    /**
     * @return BelongsToMany<Usuario, $this>
     */
    public function coordinators(): BelongsToMany
    {
        return $this->belongsToMany(Usuario::class, 'career_coordinator', 'career_id', 'user_id')
            ->withPivot('assigned_at');
    }
}

<?php

// ============================================================
// app/Models/Facultad.php
// ============================================================

namespace App\Models;

use App\Models\Concerns\StoresUppercase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facultad extends Model
{
    use StoresUppercase;

    /** @var list<string> */
    protected array $uppercase = ['nombre'];

    protected $table = 'facultad';

    protected $primaryKey = 'id_facultad';

    protected $fillable = ['nombre', 'estado'];

    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }

    /**
     * @return HasMany<Carrera, $this>
     */
    public function carreras(): HasMany
    {
        return $this->hasMany(Carrera::class, 'fk_facultad');
    }
}

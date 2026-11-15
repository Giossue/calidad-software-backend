<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Malla curricular de una carrera. Una carrera tiene una o más mallas y
 * exactamente una vigente; cada asignatura pertenece a una malla y cada
 * estudiante sigue una. Las mallas son independientes: lo aprobado en una no
 * cuenta en otra. La lógica compartida vive en `App\Support\Mallas`.
 *
 * @property int $id
 * @property int $career_id
 * @property string $name
 * @property bool $is_current
 * @property-read Carrera|null $career
 */
class Malla extends Model
{
    protected $table = 'malla';

    protected $fillable = ['career_id', 'name', 'is_current'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_current' => 'boolean', 'career_id' => 'integer'];
    }

    /** @return BelongsTo<Carrera, $this> */
    public function career(): BelongsTo
    {
        return $this->belongsTo(Carrera::class, 'career_id', 'id_carrera');
    }

    /** @return HasMany<Subject, $this> */
    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class, 'malla_id');
    }

    /**
     * Cuentas que siguen la malla; `students()` deja solo a los estudiantes.
     *
     * @return HasMany<Usuario, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(Usuario::class, 'malla_id');
    }

    /** @return HasMany<Usuario, $this> */
    public function students(): HasMany
    {
        return $this->users()->whereHas('roles', fn (Builder $query) => $query->where('slug', 'estudiante'));
    }

    /** @return HasManyThrough<AsignaturaTutoria, Subject, $this> */
    public function tutorings(): HasManyThrough
    {
        return $this->hasManyThrough(AsignaturaTutoria::class, Subject::class, 'malla_id', 'subject_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $career_id
 * @property string|null $code
 * @property string $name
 * @property int|null $cycle_number número de ciclo de la malla; el paralelo se elige en cada tutoría del período
 * @property int|null $modality_id
 * @property bool $is_active
 * @property-read Carrera|null $career
 */
class Subject extends Model
{
    protected $fillable = ['career_id', 'code', 'name', 'cycle_number', 'modality_id', 'is_active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'cycle_number' => 'integer'];
    }

    /**
     * Número de ciclo de la asignatura. Las filas registradas solo con vínculos
     * a ciclos (p. ej. por seeders antiguos) usan el menor de esos números.
     */
    public function cycleNumber(): ?int
    {
        if ($this->cycle_number !== null) {
            return $this->cycle_number;
        }
        $number = $this->relationLoaded('cycles') ? $this->cycles->min('numero') : $this->cycles()->min('ciclo.numero');

        return $number === null ? null : (int) $number;
    }

    /** @return BelongsTo<Carrera, $this> */
    public function career(): BelongsTo
    {
        return $this->belongsTo(Carrera::class, 'career_id');
    }

    /** @return BelongsTo<Modalidad, $this> */
    public function modality(): BelongsTo
    {
        return $this->belongsTo(Modalidad::class, 'modality_id');
    }

    /** @return BelongsToMany<Ciclo, $this> */
    public function cycles(): BelongsToMany
    {
        return $this->belongsToMany(Ciclo::class, 'subject_cycle', 'subject_id', 'cycle_id')
            ->withTimestamps();
    }

    /**
     * Asignaturas que continúan a esta en la malla curricular.
     *
     * @return BelongsToMany<Subject, $this>
     */
    public function nextSubjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'subject_sequences', 'subject_id', 'next_subject_id')->withTimestamps();
    }

    /**
     * Asignaturas previas: hay que aprobarlas todas para avanzar a esta.
     *
     * @return BelongsToMany<Subject, $this>
     */
    public function previousSubjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'subject_sequences', 'next_subject_id', 'subject_id')->withTimestamps();
    }

    /** @return HasMany<AsignaturaTutoria, $this> */
    public function tutorings(): HasMany
    {
        return $this->hasMany(AsignaturaTutoria::class, 'subject_id');
    }
}

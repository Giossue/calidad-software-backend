<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Versión de la malla curricular de una carrera. La malla inicial no tiene
 * período; las siguientes rigen desde `from_period_id` para quienes en ese
 * momento estén hasta el ciclo `up_to_cycle`.
 *
 * @property int $id
 * @property int $career_id
 * @property string $name
 * @property int|null $from_period_id
 * @property int|null $up_to_cycle
 * @property int|null $created_by
 * @property-read PeriodoAcademico|null $fromPeriod
 */
class Curriculum extends Model
{
    protected $table = 'curricula';

    protected $fillable = ['career_id', 'name', 'from_period_id', 'up_to_cycle', 'created_by'];

    /** @return BelongsTo<Carrera, $this> */
    public function career(): BelongsTo
    {
        return $this->belongsTo(Carrera::class, 'career_id');
    }

    /** @return BelongsTo<PeriodoAcademico, $this> */
    public function fromPeriod(): BelongsTo
    {
        return $this->belongsTo(PeriodoAcademico::class, 'from_period_id');
    }

    /** @return HasMany<Subject, $this> */
    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class, 'curriculum_id');
    }
}

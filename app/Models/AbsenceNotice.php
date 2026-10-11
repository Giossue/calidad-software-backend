<?php

namespace App\Models;

use App\Observers\AbsenceNoticeObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Aviso del estudiante de que no podrá asistir a la clase de una fecha.
 *
 * @property int $id
 * @property int $enrollment_id
 * @property Carbon $date
 * @property string $reason
 * @property-read InscripcionTutoria|null $enrollment
 */
#[ObservedBy([AbsenceNoticeObserver::class])]
class AbsenceNotice extends Model
{
    protected $fillable = ['enrollment_id', 'date', 'reason'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    /** @return BelongsTo<InscripcionTutoria, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(InscripcionTutoria::class, 'enrollment_id');
    }
}

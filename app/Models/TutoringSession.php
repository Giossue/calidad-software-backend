<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property CarbonInterface $date */
class TutoringSession extends Model
{
    protected $fillable = ['tutoring_id', 'recorded_by', 'date', 'topics_covered'];

    protected function casts(): array
    {
        return ['date' => 'date', 'topics_covered' => 'boolean'];
    }

    /** Número de la sesión dentro de su tutoría, en orden de fecha (la primera es la 1). */
    public function number(): int
    {
        return self::query()->where('tutoring_id', $this->tutoring_id)
            ->where(fn ($query) => $query->whereDate('date', '<', $this->date)
                ->orWhere(fn ($same) => $same->whereDate('date', $this->date)->where('id', '<=', $this->getKey())))
            ->count();
    }

    /** @return BelongsTo<AsignaturaTutoria, $this> */
    public function tutoring(): BelongsTo
    {
        return $this->belongsTo(AsignaturaTutoria::class, 'tutoring_id');
    }

    /** @return BelongsToMany<Tema, $this> */
    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Tema::class, 'tutoring_session_topic', 'session_id', 'topic_id');
    }

    /** @return HasMany<Asistencia, $this> */
    public function attendance(): HasMany
    {
        return $this->hasMany(Asistencia::class, 'session_id');
    }
}

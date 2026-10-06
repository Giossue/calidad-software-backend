<?php

// ============================================================
// app/Models/PeriodoAcademico.php
// ============================================================

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id_periodo
 * @property string $nombre
 * @property CarbonInterface $fecha_inicio
 * @property CarbonInterface $fecha_fin
 * @property bool $estado
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class PeriodoAcademico extends Model
{
    protected $table = 'periodo_academico';

    protected $primaryKey = 'id_periodo';

    protected $fillable = ['nombre', 'fecha_inicio', 'fecha_fin', 'estado'];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
        ];
    }

    /**
     * @return BelongsToMany<Ciclo, $this>
     */
    public function ciclos(): BelongsToMany
    {
        return $this->belongsToMany(
            Ciclo::class,
            'ciclo_periodo',
            'fk_periodo',
            'fk_ciclo'
        )->withPivot('estado')->withTimestamps();
    }

    /**
     * @return HasMany<AsignaturaTutoria, $this>
     */
    public function asignaturasTutoria(): HasMany
    {
        return $this->hasMany(AsignaturaTutoria::class, 'fk_periodo');
    }

    /**
     * @return BelongsToMany<Paralelo, $this>
     */
    public function paralelos(): BelongsToMany
    {
        return $this->belongsToMany(
            Paralelo::class,
            'periodo_paralelo',
            'fk_periodo',
            'fk_paralelo'
        )->withPivot('estado')->withTimestamps();
    }

    /**
     * Sincroniza la vigencia de los períodos académicos:
     * - Un período se habilita el día que inicia (fecha_inicio <= hoy) y se cierra en su fecha de finalización (hoy > fecha_fin).
     * - Solo puede existir un único período académico activo al mismo tiempo.
     */
    public static function sincronizarVigencia(?CarbonInterface $fechaReferencia = null): ?self
    {
        $hoy = ($fechaReferencia ?? now())->toDateString();

        // 1. Cerrar todo período activo cuya fecha de fin ya expiró o cuya fecha de inicio sea futura
        static::query()
            ->where('estado', true)
            ->where(function ($query) use ($hoy) {
                $query->where('fecha_fin', '<', $hoy)
                    ->orWhere('fecha_inicio', '>', $hoy);
            })
            ->update(['estado' => false]);

        // 2. Si no hay ningún período activo, habilitar el período cuyo inicio coincida exactamente con hoy
        // o que esté en curso si nunca fue desactivado manualmente
        $activos = static::query()->where('estado', true)->get();

        if ($activos->isEmpty()) {
            $periodoIniciaHoy = static::query()
                ->where('fecha_inicio', '<=', $hoy)
                ->where('fecha_fin', '>=', $hoy)
                ->whereDate('fecha_inicio', $hoy)
                ->first();

            if ($periodoIniciaHoy) {
                $periodoIniciaHoy->update(['estado' => true]);
                $activos = collect([$periodoIniciaHoy]);
            }
        }

        // 3. Garantizar que SOLO UN período esté activo al mismo tiempo
        if ($activos->count() > 1) {
            // Quedarse con el más adecuado en rango actual, o el más reciente
            $vigente = $activos->first(fn ($p) => $p->fecha_inicio->toDateString() <= $hoy && $p->fecha_fin->toDateString() >= $hoy)
                ?? $activos->sortByDesc('fecha_inicio')->first();

            static::query()
                ->where('id_periodo', '!=', $vigente->getKey())
                ->where('estado', true)
                ->update(['estado' => false]);

            return $vigente->refresh();
        }

        return $activos->first();
    }

    /**
     * Obtiene el único período académico vigente actual, sincronizando previamente sus vigencias.
     */
    public static function periodoVigente(): ?self
    {
        static::sincronizarVigencia();

        return static::query()->where('estado', true)->first();
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ciclo del estudiante en cada período académico. Se calcula a partir de sus
 * inscripciones activas en tutorías del período: es el número de ciclo más alto
 * entre ellas, guardado como la fila de ciclo de esas tutorías, que lleva el
 * paralelo: la que suma más inscripciones y, a igualdad, la de menor id. Si
 * ninguna fila de ese número tiene paralelo, el ciclo base (sin paralelo) de ese
 * número o, si la carrera no lo tiene, la fila de menor id. Es la misma regla de
 * `StudentCycle::syncPeriod`.
 *
 * Se rellena con las inscripciones existentes. `usuario.fk_ciclo` no se modifica
 * aquí: lo actualiza la aplicación con la siguiente inscripción del período vigente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('estudiante_periodo')) {
            Schema::create('estudiante_periodo', function (Blueprint $table): void {
                $table->increments('id_estudiante_periodo');
                $table->unsignedInteger('fk_usuario');
                $table->unsignedInteger('fk_periodo');
                $table->unsignedInteger('fk_ciclo');
                $table->timestamps();
                $table->unique(['fk_usuario', 'fk_periodo']);
                $table->index('fk_periodo');
                $table->index('fk_ciclo');
                $table->foreign('fk_usuario')->references('id_usuario')->on('usuario')->cascadeOnDelete();
                $table->foreign('fk_periodo')->references('id_periodo')->on('periodo_academico')->restrictOnDelete();
                $table->foreign('fk_ciclo')->references('id_ciclo')->on('ciclo')->restrictOnDelete();
            });
        }

        $rows = [];
        DB::table('inscripcion_tutoria')
            ->join('asignatura_tutoria', 'asignatura_tutoria.id_asig_tutoria', '=', 'inscripcion_tutoria.fk_asig_tutoria')
            ->join('ciclo', 'ciclo.id_ciclo', '=', 'asignatura_tutoria.fk_ciclo')
            ->where('inscripcion_tutoria.estado', true)
            ->whereNotNull('asignatura_tutoria.fk_periodo')
            ->orderBy('inscripcion_tutoria.id_inscripcion')
            ->select('inscripcion_tutoria.fk_id_usuario', 'asignatura_tutoria.fk_periodo', 'ciclo.id_ciclo', 'ciclo.numero', 'ciclo.fk_carrera', 'ciclo.fk_paralelo')
            ->lazy()
            ->each(function (object $row) use (&$rows): void {
                $rows[$row->fk_id_usuario.':'.$row->fk_periodo][] = $row;
            });

        $now = now();
        foreach ($rows as $group) {
            DB::table('estudiante_periodo')->updateOrInsert(
                ['fk_usuario' => $group[0]->fk_id_usuario, 'fk_periodo' => $group[0]->fk_periodo],
                ['fk_ciclo' => $this->periodCycle($group), 'created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    /** @param  list<object>  $rows  una fila de ciclo por inscripción activa del estudiante en el período */
    private function periodCycle(array $rows): int
    {
        $top = max(array_map(fn (object $row): int => (int) $row->numero, $rows));
        $atTop = array_values(array_filter($rows, fn (object $row): bool => (int) $row->numero === $top));

        $counts = [];
        foreach ($atTop as $row) {
            if ($row->fk_paralelo !== null) {
                $counts[(int) $row->id_ciclo] = ($counts[(int) $row->id_ciclo] ?? 0) + 1;
            }
        }
        if ($counts !== []) {
            $best = max($counts);

            return min(array_keys(array_filter($counts, fn (int $count): bool => $count === $best)));
        }

        usort($atTop, fn (object $a, object $b): int => (int) $a->id_ciclo <=> (int) $b->id_ciclo);

        return (int) (DB::table('ciclo')->where('fk_carrera', $atTop[0]->fk_carrera)->where('numero', $top)
            ->whereNull('fk_paralelo')->orderByDesc('estado')->orderBy('id_ciclo')->value('id_ciclo') ?? $atTop[0]->id_ciclo);
    }

    public function down(): void
    {
        Schema::dropIfExists('estudiante_periodo');
    }
};

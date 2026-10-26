<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reclasifica las métricas de conocimiento con la escala oficial del formato
 * "Reporte de la Evaluación Diagnóstica": Bajo 0%-50%, Medio 51%-69%, Alto 70%-100%.
 * Las métricas existentes se calcularon con la escala provisional (Bajo < 4).
 */
return new class extends Migration
{
    private const OFFICIAL = [
        ['key' => 'low', 'label' => 'Bajo', 'min' => 0, 'max' => 5],
        ['key' => 'medium', 'label' => 'Medio', 'min' => 5.01, 'max' => 6.99],
        ['key' => 'high', 'label' => 'Alto', 'min' => 7, 'max' => 10],
    ];

    private const PROVISIONAL = [
        ['key' => 'low', 'label' => 'Bajo', 'min' => 0, 'max' => 3.99],
        ['key' => 'medium', 'label' => 'Medio', 'min' => 4, 'max' => 6.99],
        ['key' => 'high', 'label' => 'Alto', 'min' => 7, 'max' => 10],
    ];

    public function up(): void
    {
        $this->reclassify(self::OFFICIAL);
    }

    public function down(): void
    {
        $this->reclassify(self::PROVISIONAL);
    }

    /** @param list<array{key: string, label: string, min: float|int, max: float|int}> $groups */
    private function reclassify(array $groups): void
    {
        if (! Schema::hasTable('metrica_conocimiento') || ! Schema::hasColumn('metrica_conocimiento', 'enrollment_id')) {
            return;
        }

        DB::table('metrica_conocimiento')->whereNotNull('enrollment_id')->orderBy('id_metrica')
            ->each(function (object $metric) use ($groups): void {
                $grade = DB::table('nota')
                    ->where('fk_inscripcion', $metric->enrollment_id)
                    ->where('tipo', 'diagnostic')
                    ->orderByDesc('id_nota')
                    ->value('valor');

                if ($grade === null) {
                    return;
                }

                $group = collect($groups)->first(fn (array $item) => (float) $grade >= $item['min'] && (float) $grade <= $item['max']);
                if ($group === null) {
                    return;
                }

                DB::table('metrica_conocimiento')->where('id_metrica', $metric->id_metrica)->update([
                    'descripcion' => $group['label'],
                    'rango' => $group['key'],
                    'nota_minima' => $group['min'],
                    'nota_maxima' => $group['max'],
                    'updated_at' => now(),
                ]);
            });
    }
};

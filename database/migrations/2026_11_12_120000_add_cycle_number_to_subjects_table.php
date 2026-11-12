<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La asignatura pertenece a un número de ciclo de la malla, sin paralelos: el
 * paralelo se elige al crear cada tutoría del período.
 *
 * Se rellena con el menor número entre los ciclos que la asignatura tenía
 * vinculados. Los vínculos previos de `subject_cycle` se conservan intactos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('subjects', 'cycle_number')) {
            Schema::table('subjects', function (Blueprint $table): void {
                $table->unsignedSmallInteger('cycle_number')->nullable()->after('name');
                $table->index(['career_id', 'cycle_number']);
            });
        }

        DB::table('subjects')->whereNull('cycle_number')->update([
            'cycle_number' => DB::table('subject_cycle')
                ->join('ciclo', 'ciclo.id_ciclo', '=', 'subject_cycle.cycle_id')
                ->whereColumn('subject_cycle.subject_id', 'subjects.id')
                ->selectRaw('min(ciclo.numero)'),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('subjects', 'cycle_number')) {
            Schema::table('subjects', function (Blueprint $table): void {
                $table->dropIndex(['career_id', 'cycle_number']);
                $table->dropColumn('cycle_number');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cada malla curricular es una carrera con su propio código: dos carreras
 * pueden compartir el nombre siempre que su código sea distinto. Las
 * carreras existentes conservan un código nulo hasta que el administrador
 * lo registre.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('carrera')) {
            return;
        }

        DB::transaction(function (): void {
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement('LOCK TABLE carrera IN ACCESS EXCLUSIVE MODE');
            }

            if (! Schema::hasColumn('carrera', 'codigo')) {
                Schema::table('carrera', function (Blueprint $table): void {
                    $table->string('codigo', 30)->nullable()->unique()->after('nombre');
                });
            }

            if (Schema::hasIndex('carrera', ['nombre'], 'unique')) {
                Schema::table('carrera', function (Blueprint $table): void {
                    $table->dropUnique(['nombre']);
                });
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('carrera')) {
            return;
        }

        DB::transaction(function (): void {
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement('LOCK TABLE carrera IN ACCESS EXCLUSIVE MODE');
            }

            $duplicateNames = DB::table('carrera')
                ->select('nombre')
                ->groupBy('nombre')
                ->havingRaw('COUNT(*) > 1')
                ->orderBy('nombre')
                ->pluck('nombre');

            // Volver al nombre único obligaría a renombrar o fusionar carreras
            // (mallas) distintas; se detiene para no perder datos en silencio.
            if ($duplicateNames->isNotEmpty()) {
                throw new RuntimeException(
                    'No se puede revertir: varias carreras comparten el nombre ('.$duplicateNames->implode(', ').'). '
                    .'Renómbralas antes de volver al nombre único.',
                );
            }

            if (Schema::hasColumn('carrera', 'codigo')) {
                Schema::table('carrera', function (Blueprint $table): void {
                    $table->dropUnique(['codigo']);
                });
                Schema::table('carrera', function (Blueprint $table): void {
                    $table->dropColumn('codigo');
                });
            }

            if (! Schema::hasIndex('carrera', ['nombre'], 'unique')) {
                Schema::table('carrera', function (Blueprint $table): void {
                    $table->unique('nombre');
                });
            }
        });
    }
};

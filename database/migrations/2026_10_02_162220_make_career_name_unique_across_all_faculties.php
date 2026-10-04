<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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

            $this->disambiguateExistingCareerNames();

            if (Schema::hasIndex('carrera', 'carrera_fk_facultad_nombre_unique', 'unique')) {
                Schema::table('carrera', function (Blueprint $table): void {
                    $table->dropUnique(['fk_facultad', 'nombre']);
                });
            }

            if (! Schema::hasIndex('carrera', ['nombre'], 'unique')) {
                Schema::table('carrera', function (Blueprint $table): void {
                    $table->unique('nombre');
                });
            }
        });
    }

    private function disambiguateExistingCareerNames(): void
    {
        $duplicateNames = DB::table('carrera')
            ->select('nombre')
            ->groupBy('nombre')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('nombre')
            ->pluck('nombre');

        foreach ($duplicateNames as $originalName) {
            $careers = DB::table('carrera')
                ->join('facultad', 'facultad.id_facultad', '=', 'carrera.fk_facultad')
                ->where('carrera.nombre', $originalName)
                ->orderBy('carrera.id_carrera')
                ->get(['carrera.id_carrera', 'facultad.nombre as faculty_name']);

            // La carrera de menor ID conserva el nombre y todas mantienen sus
            // IDs, estados y relaciones: no se fusionan ni eliminan registros.
            foreach ($careers->slice(1) as $career) {
                $attempt = 0;

                do {
                    $candidate = $this->careerNameWithFaculty(
                        $originalName,
                        $career->faculty_name,
                        $career->id_carrera,
                        $attempt++,
                    );
                } while (DB::table('carrera')->where('nombre', $candidate)->exists());

                DB::table('carrera')->where('id_carrera', $career->id_carrera)->update(['nombre' => $candidate]);
            }
        }
    }

    private function careerNameWithFaculty(string $careerName, string $facultyName, int $careerId, int $attempt): string
    {
        $identifier = $attempt === 0 ? '' : ' ['.$careerId.($attempt > 1 ? '-'.$attempt : '').']';
        // Se reserva al menos un carácter para la carrera y se conserva el
        // cierre del paréntesis incluso cuando ambos nombres son largos.
        $facultyName = Str::substr($facultyName, 0, 150 - Str::length($identifier) - 4);
        $suffix = ' ('.$facultyName.')'.$identifier;

        return Str::substr($careerName, 0, 150 - Str::length($suffix)).$suffix;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('carrera')) {
            Schema::table('carrera', function (Blueprint $table): void {
                $table->dropUnique(['nombre']);
                $table->unique(['fk_facultad', 'nombre']);
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ciclo que cursa el estudiante. Hasta ahora se deducía de su paralelo, pero un
 * paralelo (p. ej. "A") es compartido por todos los ciclos, así que no lo identifica.
 *
 * Se rellena con el ciclo de la inscripción activa más reciente del estudiante en
 * una tutoría; si no tiene, con el único ciclo de su carrera que use alguno de sus
 * paralelos. Los demás quedan sin ciclo para que coordinación lo asigne.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('usuario', 'fk_ciclo')) {
            Schema::table('usuario', function (Blueprint $table): void {
                $table->unsignedInteger('fk_ciclo')->nullable()->after('fk_carrera');
                $table->foreign('fk_ciclo')->references('id_ciclo')->on('ciclo')->nullOnDelete();
                $table->index('fk_ciclo');
            });
        }

        $studentIds = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('roles.slug', 'estudiante')
            ->pluck('role_user.user_id');

        DB::table('usuario')->whereIn('id_usuario', $studentIds)->whereNull('fk_ciclo')->orderBy('id_usuario')
            ->each(function (object $student): void {
                $cycleId = DB::table('inscripcion_tutoria')
                    ->join('asignatura_tutoria', 'asignatura_tutoria.id_asig_tutoria', '=', 'inscripcion_tutoria.fk_asig_tutoria')
                    ->where('inscripcion_tutoria.fk_id_usuario', $student->id_usuario)
                    ->where('inscripcion_tutoria.estado', true)
                    ->orderByDesc('inscripcion_tutoria.fecha_inscripcion')
                    ->orderByDesc('inscripcion_tutoria.id_inscripcion')
                    ->value('asignatura_tutoria.fk_ciclo');

                if ($cycleId === null && $student->fk_carrera !== null && Schema::hasTable('usuario_paralelo')) {
                    $candidates = DB::table('ciclo')
                        ->join('usuario_paralelo', 'usuario_paralelo.fk_paralelo', '=', 'ciclo.fk_paralelo')
                        ->where('usuario_paralelo.fk_usuario', $student->id_usuario)
                        ->where('usuario_paralelo.estado', true)
                        ->where('ciclo.fk_carrera', $student->fk_carrera)
                        ->distinct()
                        ->pluck('ciclo.id_ciclo');
                    $cycleId = $candidates->count() === 1 ? $candidates->first() : null;
                }

                if ($cycleId === null) {
                    return;
                }

                $careerId = DB::table('ciclo')->where('id_ciclo', $cycleId)->value('fk_carrera');
                DB::table('usuario')->where('id_usuario', $student->id_usuario)->update([
                    'fk_ciclo' => $cycleId,
                    'fk_carrera' => $student->fk_carrera ?? $careerId,
                ]);
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('usuario', 'fk_ciclo')) {
            Schema::table('usuario', function (Blueprint $table): void {
                $table->dropForeign(['fk_ciclo']);
                $table->dropIndex(['fk_ciclo']);
                $table->dropColumn('fk_ciclo');
            });
        }
    }
};

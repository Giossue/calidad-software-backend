<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El tutorial pasa a tener un recorrido por pantalla. `completed_tours` guarda los
 * identificadores de los recorridos que el usuario ya vio («general», «users»,
 * «teacher-tutorings/grades»…). Quien ya vio el recorrido general lo conserva.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('usuario', 'completed_tours')) {
            Schema::table('usuario', function (Blueprint $table): void {
                $table->json('completed_tours')->nullable();
            });
        }

        if (Schema::hasColumn('usuario', 'tour_completed_at')) {
            DB::table('usuario')->whereNotNull('tour_completed_at')->update(['completed_tours' => json_encode(['general'])]);

            Schema::table('usuario', function (Blueprint $table): void {
                $table->dropColumn('tour_completed_at');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('usuario', 'tour_completed_at')) {
            Schema::table('usuario', function (Blueprint $table): void {
                $table->timestamp('tour_completed_at')->nullable();
            });
        }

        if (Schema::hasColumn('usuario', 'completed_tours')) {
            DB::table('usuario')->whereNotNull('completed_tours')->update(['tour_completed_at' => now()]);

            Schema::table('usuario', function (Blueprint $table): void {
                $table->dropColumn('completed_tours');
            });
        }
    }
};

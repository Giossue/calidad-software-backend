<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('usuario', 'fk_carrera')) {
            Schema::table('usuario', function (Blueprint $table): void {
                $table->unsignedInteger('fk_carrera')->nullable()->after('estado');
                $table->foreign('fk_carrera')
                    ->references('id_carrera')
                    ->on('carrera')
                    ->nullOnDelete();
            });
        }

        // Backfill existing coordinators and teachers if they have assigned careers
        if (Schema::hasTable('career_coordinator')) {
            $coordinators = DB::table('career_coordinator')->select('user_id', 'career_id')->get();
            foreach ($coordinators as $c) {
                DB::table('usuario')->where('id_usuario', $c->user_id)->whereNull('fk_carrera')->update(['fk_carrera' => $c->career_id]);
            }
        }

        if (Schema::hasTable('career_teacher')) {
            $teachers = DB::table('career_teacher')->select('user_id', 'career_id')->get();
            foreach ($teachers as $t) {
                DB::table('usuario')->where('id_usuario', $t->user_id)->whereNull('fk_carrera')->update(['fk_carrera' => $t->career_id]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('usuario', 'fk_carrera')) {
            Schema::table('usuario', function (Blueprint $table): void {
                $table->dropForeign(['fk_carrera']);
                $table->dropColumn('fk_carrera');
            });
        }
    }
};

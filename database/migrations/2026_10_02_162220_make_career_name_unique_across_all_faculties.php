<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('carrera')) {
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE carrera DROP CONSTRAINT IF EXISTS carrera_fk_facultad_nombre_unique');
            } else {
                Schema::table('carrera', function (Blueprint $table): void {
                    $table->dropUnique(['fk_facultad', 'nombre']);
                });
            }

            Schema::table('carrera', function (Blueprint $table): void {
                $table->unique('nombre');
            });
        }
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

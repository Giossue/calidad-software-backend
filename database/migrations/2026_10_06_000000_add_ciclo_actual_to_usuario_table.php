<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ciclo que cursa el estudiante: el último ciclo de su carrera es
     * titulación; los anteriores, tutorías. Nulo para otros roles y para los
     * estudiantes registrados antes de este cambio.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('usuario', 'ciclo_actual')) {
            Schema::table('usuario', function (Blueprint $table): void {
                $table->unsignedSmallInteger('ciclo_actual')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('usuario', 'ciclo_actual')) {
            Schema::table('usuario', function (Blueprint $table): void {
                $table->dropColumn('ciclo_actual');
            });
        }
    }
};

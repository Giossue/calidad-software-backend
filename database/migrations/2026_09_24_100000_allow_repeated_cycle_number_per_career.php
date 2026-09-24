<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permite paralelos del mismo ciclo (ej. "Primer Ciclo A" y "Primer Ciclo B",
     * ambos numero=1) dentro de una carrera. La combinación única pasa de
     * (fk_carrera, numero) a (fk_carrera, numero, nombre).
     */
    public function up(): void
    {
        Schema::table('ciclo', function (Blueprint $table): void {
            $table->dropUnique(['fk_carrera', 'numero']);
            $table->unique(['fk_carrera', 'numero', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::table('ciclo', function (Blueprint $table): void {
            $table->dropUnique(['fk_carrera', 'numero', 'nombre']);
            $table->unique(['fk_carrera', 'numero']);
        });
    }
};

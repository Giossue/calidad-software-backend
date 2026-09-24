<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permite paralelos del mismo ciclo (ej. "Primer Ciclo A" y "Primer Ciclo B",
     * ambos numero=1) dentro de una carrera. La combinación única pasa de
     * (fk_carrera, numero) a (fk_carrera, numero, nombre).
     *
     * ciclo pertenece al baseline no versionado (igual que paralelo): su
     * constraint unique real se llama "ciclo_carrera_numero_unique", no
     * "ciclo_fk_carrera_numero_unique" que generaría la convención de
     * Laravel. Se dropea por su nombre real en vez de adivinarlo.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            // "IF EXISTS" cubre tanto el nombre real de producción como el que
            // generaría la convención de Laravel (por si esta migración corre
            // alguna vez sobre un Postgres creado desde cero por nuestras
            // propias migraciones, no sobre el baseline).
            DB::statement('ALTER TABLE ciclo DROP CONSTRAINT IF EXISTS ciclo_carrera_numero_unique');
            DB::statement('ALTER TABLE ciclo DROP CONSTRAINT IF EXISTS ciclo_fk_carrera_numero_unique');
        } else {
            // SQLite (tests): ciclo siempre se crea desde cero con nuestra
            // propia migración, así que el nombre por convención es correcto.
            Schema::table('ciclo', function (Blueprint $table): void {
                $table->dropUnique(['fk_carrera', 'numero']);
            });
        }

        Schema::table('ciclo', function (Blueprint $table): void {
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

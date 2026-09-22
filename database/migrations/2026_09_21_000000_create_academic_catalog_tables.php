<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('facultad')) {
            Schema::create('facultad', function (Blueprint $table): void {
                $table->increments('id_facultad');
                $table->string('nombre', 150);
                $table->boolean('estado')->default(true);
                $table->timestampsTz();
            });
        }

        if (! Schema::hasTable('carrera')) {
            Schema::create('carrera', function (Blueprint $table): void {
                $table->increments('id_carrera');
                $table->unsignedInteger('fk_facultad');
                $table->string('nombre', 150);
                $table->boolean('estado')->default(true);
                $table->timestampsTz();

                $table->foreign('fk_facultad')
                    ->references('id_facultad')
                    ->on('facultad')
                    ->restrictOnDelete();
                $table->unique(['fk_facultad', 'nombre']);
            });
        }

        if (! Schema::hasTable('ciclo')) {
            Schema::create('ciclo', function (Blueprint $table): void {
                $table->increments('id_ciclo');
                $table->unsignedInteger('fk_carrera');
                $table->string('nombre', 100);
                $table->unsignedInteger('numero');
                $table->boolean('estado')->default(true);
                $table->timestampsTz();

                $table->foreign('fk_carrera')
                    ->references('id_carrera')
                    ->on('carrera')
                    ->restrictOnDelete();
                $table->unique(['fk_carrera', 'numero']);
            });

            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement(
                    'ALTER TABLE ciclo ADD CONSTRAINT ciclo_numero_positivo CHECK (numero > 0)',
                );
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Las tablas de catálogos académicos pueden pertenecer al baseline desplegado y no admiten rollback automático.',
        );
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('modalidad')) {
            Schema::create('modalidad', function (Blueprint $table): void {
                $table->increments('id_modalidad');
                $table->string('nombre', 100)->unique();
                $table->boolean('estado')->default(true);
                $table->timestampsTz();
            });
        }

        if (! Schema::hasColumn('carrera', 'fk_modalidad')) {
            Schema::table('carrera', function (Blueprint $table): void {
                $table->unsignedInteger('fk_modalidad')->nullable()->after('fk_facultad');
                $table->foreign('fk_modalidad')
                    ->references('id_modalidad')
                    ->on('modalidad')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('carrera', 'fk_modalidad')) {
            Schema::table('carrera', function (Blueprint $table): void {
                $table->dropForeign(['fk_modalidad']);
                $table->dropColumn('fk_modalidad');
            });
        }

        // No se revierte la creación de `modalidad`: puede ser la tabla real del entorno desplegado.
    }
};

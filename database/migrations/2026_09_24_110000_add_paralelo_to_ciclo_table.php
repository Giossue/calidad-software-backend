<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * paralelo pertenece al baseline no versionado (igual que usuario_paralelo);
     * se crea aquí solo si todavía no existe, para que entornos nuevos (tests,
     * local) queden con la misma forma que producción.
     *
     * fk_paralelo reemplaza a "nombre" como diferenciador de ciclos repetidos:
     * "Primer Ciclo" puede repetirse en la misma carrera si cada fila apunta a
     * un paralelo distinto (A, B, ...). Dos ciclos sin paralelo asignado no
     * están protegidos entre sí por la unicidad (NULL no es igual a NULL en
     * PostgreSQL); eso es aceptable porque solo se necesita paralelo cuando de
     * verdad hay más de un grupo en el mismo nivel.
     */
    public function up(): void
    {
        if (! Schema::hasTable('paralelo')) {
            Schema::create('paralelo', function (Blueprint $table): void {
                $table->increments('id_paralelo');
                $table->string('nombre', 50);
                $table->boolean('estado')->default(true);
                $table->timestampsTz();
            });
        }

        Schema::table('ciclo', function (Blueprint $table): void {
            $table->dropUnique(['fk_carrera', 'numero', 'nombre']);

            $table->unsignedInteger('fk_paralelo')->nullable()->after('numero');
            $table->foreign('fk_paralelo')
                ->references('id_paralelo')
                ->on('paralelo')
                ->restrictOnDelete();

            $table->unique(['fk_carrera', 'numero', 'fk_paralelo']);
        });
    }

    public function down(): void
    {
        Schema::table('ciclo', function (Blueprint $table): void {
            $table->dropUnique(['fk_carrera', 'numero', 'fk_paralelo']);
            $table->dropForeign(['fk_paralelo']);
            $table->dropColumn('fk_paralelo');
            $table->unique(['fk_carrera', 'numero', 'nombre']);
        });
    }
};

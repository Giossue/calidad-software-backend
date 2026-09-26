<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * observacion_titulacion forma parte del baseline institucional;
     * se crea condicionalmente solo si no existe en el entorno actual.
     */
    public function up(): void
    {
        if (! Schema::hasTable('observacion_titulacion')) {
            Schema::create('observacion_titulacion', function (Blueprint $table): void {
                $table->increments('id_obs_tit');
                $table->unsignedInteger('fk_tema_tit');
                $table->unsignedInteger('fk_coord_tit');
                $table->text('descripcion');
                $table->date('fecha_registro')->nullable();
                $table->timestampsTz();

                $table->foreign('fk_tema_tit')
                    ->references('id_tema_tit')
                    ->on('tema_titulacion')
                    ->cascadeOnDelete();

                $table->foreign('fk_coord_tit')
                    ->references('id_usuario')
                    ->on('usuario')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('observacion_titulacion');
    }
};

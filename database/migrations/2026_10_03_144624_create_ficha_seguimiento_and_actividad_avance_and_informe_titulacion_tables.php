<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ficha_seguimiento', function (Blueprint $table) {
            $table->id('id_ficha');
            $table->foreignId('fk_tema_tit')->constrained('tema_titulacion', 'id_tema_tit')->cascadeOnDelete();
            $table->date('fecha_apertura')->default(now()->toDateString());
            $table->decimal('porcentaje_avance', 5, 2)->default(0.00);
            $table->string('estado', 50)->default('en_progreso');
            $table->timestamps();
        });

        Schema::create('actividad_avance', function (Blueprint $table) {
            $table->id('id_actividad_av');
            $table->foreignId('fk_ficha')->constrained('ficha_seguimiento', 'id_ficha')->cascadeOnDelete();
            $table->foreignId('fk_docente')->nullable()->constrained('usuario', 'id_usuario')->nullOnDelete();
            $table->text('descripcion');
            $table->boolean('completada')->default(false);
            $table->date('fecha_registro')->default(now()->toDateString());
            $table->timestamps();
        });

        Schema::create('informe_titulacion', function (Blueprint $table) {
            $table->id('id_informe');
            $table->foreignId('fk_ficha')->constrained('ficha_seguimiento', 'id_ficha')->cascadeOnDelete();
            $table->foreignId('fk_coord_tit')->nullable()->constrained('usuario', 'id_usuario')->nullOnDelete();
            $table->date('fecha_generacion')->default(now()->toDateString());
            $table->text('observaciones_finales')->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('informe_titulacion');
        Schema::dropIfExists('actividad_avance');
        Schema::dropIfExists('ficha_seguimiento');
    }
};

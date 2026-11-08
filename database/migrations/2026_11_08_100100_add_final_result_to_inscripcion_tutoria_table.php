<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Resultado final que registra el docente al cerrar el ciclo: `approved` o `failed`. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inscripcion_tutoria', function (Blueprint $table): void {
            $table->string('final_result', 20)->nullable();
            $table->timestampTz('final_result_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inscripcion_tutoria', function (Blueprint $table): void {
            $table->dropColumn(['final_result', 'final_result_at']);
        });
    }
};

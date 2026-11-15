<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Abandono de la tutoría: cuándo lo registraron y el motivo en texto libre. Ambos nulos si no abandonó. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inscripcion_tutoria', function (Blueprint $table): void {
            $table->timestampTz('abandoned_at')->nullable();
            $table->text('abandonment_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inscripcion_tutoria', function (Blueprint $table): void {
            $table->dropColumn(['abandoned_at', 'abandonment_reason']);
        });
    }
};

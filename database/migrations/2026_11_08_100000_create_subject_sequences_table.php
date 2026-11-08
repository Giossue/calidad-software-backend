<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Secuencia de la malla curricular: `next_subject_id` continúa a `subject_id`.
 * Una asignatura puede tener varias previas (Cálculo II viene de Cálculo I y
 * Álgebra Lineal) y continuar en varias.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_sequences', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('subject_id');
            $table->unsignedInteger('next_subject_id');
            $table->timestampsTz();

            $table->foreign('subject_id')->references('id')->on('subjects')->cascadeOnDelete();
            $table->foreign('next_subject_id')->references('id')->on('subjects')->cascadeOnDelete();
            $table->unique(['subject_id', 'next_subject_id']);
            $table->index('next_subject_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_sequences');
    }
};

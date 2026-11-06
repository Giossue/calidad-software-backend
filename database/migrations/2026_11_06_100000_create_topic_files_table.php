<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Recursos (PDF, Word, PowerPoint…) que el docente adjunta a un tema de tutoría. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topic_files', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('topic_id');
            $table->unsignedInteger('uploaded_by')->nullable();
            $table->string('original_name', 255);
            $table->string('path', 500);
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size');
            $table->timestampsTz();

            $table->foreign('topic_id')->references('id_tema')->on('tema')->cascadeOnDelete();
            $table->foreign('uploaded_by')->references('id_usuario')->on('usuario')->nullOnDelete();
            $table->index('topic_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topic_files');
    }
};

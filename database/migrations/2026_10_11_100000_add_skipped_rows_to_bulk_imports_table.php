<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las filas cuyos datos ya existen en el sistema se informan como avisos
 * (omitidas) y no como errores.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bulk_imports', function (Blueprint $table): void {
            $table->unsignedInteger('skipped_count')->default(0)->after('created_count');
            $table->json('warnings')->nullable()->after('errors');
        });
    }

    public function down(): void
    {
        Schema::table('bulk_imports', function (Blueprint $table): void {
            $table->dropColumn(['skipped_count', 'warnings']);
        });
    }
};

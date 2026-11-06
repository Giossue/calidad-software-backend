<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Momento en que el usuario terminó o cerró el tutorial del sistema. Mientras sea
 * nulo, el tutorial se le presenta automáticamente al ingresar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('usuario', 'tour_completed_at')) {
            Schema::table('usuario', function (Blueprint $table): void {
                $table->timestamp('tour_completed_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('usuario', 'tour_completed_at')) {
            Schema::table('usuario', function (Blueprint $table): void {
                $table->dropColumn('tour_completed_at');
            });
        }
    }
};

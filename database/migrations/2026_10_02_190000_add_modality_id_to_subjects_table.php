<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('subjects', 'modality_id')) {
            Schema::table('subjects', function (Blueprint $table) {
                $table->unsignedInteger('modality_id')->nullable()->after('name');
                $table->foreign('modality_id')->references('id_modalidad')->on('modalidad')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('subjects', 'modality_id')) {
            Schema::table('subjects', function (Blueprint $table) {
                $table->dropForeign(['modality_id']);
                $table->dropColumn('modality_id');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE usuario ALTER COLUMN cedula DROP NOT NULL');
        } else {
            Schema::table('usuario', function (Blueprint $table): void {
                $table->string('cedula', 20)->nullable()->change();
            });
        }

        if (! Schema::hasColumn('usuario', 'must_complete_profile')) {
            Schema::table('usuario', function (Blueprint $table): void {
                $table->boolean('must_complete_profile')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('usuario', 'must_complete_profile')) {
            Schema::table('usuario', function (Blueprint $table): void {
                $table->dropColumn('must_complete_profile');
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE usuario ALTER COLUMN cedula SET NOT NULL');
        }
    }
};

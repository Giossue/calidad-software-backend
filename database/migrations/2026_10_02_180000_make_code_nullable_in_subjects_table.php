<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE subjects ALTER COLUMN code DROP NOT NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("UPDATE subjects SET code = CONCAT('SUB-', id) WHERE code IS NULL");
            DB::statement('ALTER TABLE subjects ALTER COLUMN code SET NOT NULL');
        }
    }
};

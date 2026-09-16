<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && DB::table('users')->exists()) {
            throw new RuntimeException('No se puede eliminar users porque contiene registros.');
        }

        if (Schema::hasTable('passkeys') && DB::table('passkeys')->exists()) {
            throw new RuntimeException('No se puede eliminar passkeys porque contiene registros.');
        }

        Schema::table('usuario', function (Blueprint $table): void {
            $table->timestampTz('email_verified_at')->nullable();
            $table->rememberToken();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestampTz('two_factor_confirmed_at')->nullable();
        });

        Schema::dropIfExists('passkeys');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('users');
    }

    public function down(): void
    {
        throw new RuntimeException('La consolidación de identidad en usuario no admite rollback automático.');
    }
};

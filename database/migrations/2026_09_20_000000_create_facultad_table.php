<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('facultad')) {
            return;
        }

        Schema::create('facultad', function (Blueprint $table): void {
            $table->increments('id_facultad');
            $table->string('nombre', 150);
            $table->boolean('estado')->default(true);
            $table->timestampsTz();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX facultad_nombre_lower_unique ON facultad (lower(nombre))');
            DB::statement("ALTER TABLE facultad ADD CONSTRAINT facultad_nombre_no_vacio CHECK (btrim(nombre) <> '')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('facultad');
    }
};

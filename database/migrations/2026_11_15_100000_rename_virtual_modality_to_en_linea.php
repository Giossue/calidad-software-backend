<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La modalidad "Virtual" pasa a llamarse "En línea". Se renombra el mismo registro, así
 * las carreras, asignaturas y tutorías que la usan la conservan. Si "En línea" ya existe
 * (el nombre es único), no se toca nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rename('Virtual', 'En línea');
    }

    public function down(): void
    {
        $this->rename('En línea', 'Virtual');
    }

    private function rename(string $from, string $to): void
    {
        if (DB::table('modalidad')->where('nombre', $to)->exists()) {
            return;
        }

        DB::table('modalidad')->where('nombre', $from)->update(['nombre' => $to]);
    }
};

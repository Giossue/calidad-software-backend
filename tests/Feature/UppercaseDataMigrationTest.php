<?php

namespace Tests\Feature;

use App\Models\Facultad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UppercaseDataMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_names_are_converted_to_uppercase(): void
    {
        $faculty = Facultad::query()->create(['nombre' => 'X', 'estado' => true]);
        // Simula un registro guardado antes del cambio, sin pasar por el modelo.
        DB::table('facultad')->where('id_facultad', $faculty->getKey())->update(['nombre' => 'Ciencias de la Educación']);

        $migration = require database_path('migrations/2026_11_26_100000_uppercase_names_catalogs_topics_and_titles.php');
        $migration->up();

        $this->assertSame('CIENCIAS DE LA EDUCACIÓN', DB::table('facultad')->where('id_facultad', $faculty->getKey())->value('nombre'));
    }
}

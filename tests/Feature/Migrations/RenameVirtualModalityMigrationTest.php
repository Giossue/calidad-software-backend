<?php

namespace Tests\Feature\Migrations;

use App\Models\Modalidad;
use Database\Seeders\ModalidadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** La modalidad "Virtual" pasa a llamarse "En línea", conservando su id y lo que la usa. */
class RenameVirtualModalityMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_11_15_100000_rename_virtual_modality_to_en_linea.php';

    public function test_it_renames_virtual_to_en_linea_keeping_the_record(): void
    {
        $virtual = Modalidad::query()->create(['nombre' => 'Virtual', 'estado' => true]);
        Modalidad::query()->create(['nombre' => 'Presencial', 'estado' => true]);

        $migration = require database_path(self::MIGRATION);
        $migration->up();

        $this->assertSame('En línea', $virtual->refresh()->nombre);
        $this->assertSame(['En línea', 'Presencial'], Modalidad::query()->orderBy('nombre')->pluck('nombre')->all());

        $migration->down();
        $this->assertSame('Virtual', $virtual->refresh()->nombre);
    }

    public function test_it_does_nothing_when_en_linea_already_exists(): void
    {
        $virtual = Modalidad::query()->create(['nombre' => 'Virtual', 'estado' => true]);
        Modalidad::query()->create(['nombre' => 'En línea', 'estado' => true]);

        (require database_path(self::MIGRATION))->up();

        $this->assertSame('Virtual', $virtual->refresh()->nombre);
    }

    public function test_the_seeder_creates_en_linea_instead_of_virtual(): void
    {
        $this->seed(ModalidadSeeder::class);

        $this->assertSame(['En línea', 'Híbrida', 'Presencial'], Modalidad::query()->orderBy('nombre')->pluck('nombre')->all());
    }
}

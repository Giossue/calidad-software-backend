<?php

namespace Tests\Feature\Migrations;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class CareerCodeMigrationTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = DB::getDefaultConnection();
        config(['database.connections.career_code_migration_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::setDefaultConnection('career_code_migration_test');
        Schema::create('carrera', function (Blueprint $table): void {
            $table->increments('id_carrera');
            $table->unsignedInteger('fk_facultad');
            $table->string('nombre', 150);
            $table->boolean('estado')->default(true);
            $table->timestamps();
            $table->unique('nombre');
        });
        DB::table('carrera')->insert([
            ['id_carrera' => 1, 'fk_facultad' => 1, 'nombre' => 'Software', 'estado' => true],
            ['id_carrera' => 2, 'fk_facultad' => 1, 'nombre' => 'Medicina', 'estado' => false],
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('career_code_migration_test');
        DB::setDefaultConnection($this->originalConnection);

        parent::tearDown();
    }

    public function test_existing_careers_keep_a_null_code_and_the_name_may_repeat_with_another_code(): void
    {
        $this->migration()->up();

        $this->assertSame([1 => null, 2 => null], DB::table('carrera')->orderBy('id_carrera')->pluck('codigo', 'id_carrera')->all());

        DB::table('carrera')->insert(['fk_facultad' => 1, 'nombre' => 'Software', 'codigo' => 'SW-2024']);
        $this->assertSame(2, DB::table('carrera')->where('nombre', 'Software')->count());

        $this->expectException(QueryException::class);
        DB::table('carrera')->insert(['fk_facultad' => 1, 'nombre' => 'Telemática', 'codigo' => 'SW-2024']);
    }

    public function test_rollback_restores_the_unique_name_when_no_name_repeats(): void
    {
        $migration = $this->migration();
        $migration->up();
        DB::table('carrera')->where('id_carrera', 1)->update(['codigo' => 'SW-2020']);

        $migration->down();

        $this->assertFalse(Schema::hasColumn('carrera', 'codigo'));
        $this->assertTrue(Schema::hasIndex('carrera', ['nombre'], 'unique'));
        $this->assertSame(2, DB::table('carrera')->count());
    }

    public function test_rollback_refuses_while_two_careers_share_a_name(): void
    {
        $migration = $this->migration();
        $migration->up();
        DB::table('carrera')->insert(['fk_facultad' => 1, 'nombre' => 'Software', 'codigo' => 'SW-2024']);

        try {
            $migration->down();
            $this->fail('The rollback should refuse duplicated career names.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Software', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('carrera', 'codigo'));
        $this->assertSame(3, DB::table('carrera')->count());
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_11_11_100000_add_code_to_careers.php');
    }
}

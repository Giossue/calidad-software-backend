<?php

namespace Tests\Feature\Migrations;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CareerNameUniquenessMigrationTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = DB::getDefaultConnection();
        config(['database.connections.career_name_migration_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::setDefaultConnection('career_name_migration_test');
        Schema::create('facultad', function (Blueprint $table): void {
            $table->increments('id_facultad');
            $table->string('nombre', 150);
        });
        Schema::create('carrera', function (Blueprint $table): void {
            $table->increments('id_carrera');
            $table->unsignedInteger('fk_facultad');
            $table->string('nombre', 150);
            $table->boolean('estado')->default(true);
            $table->timestamps();
            $table->foreign('fk_facultad')->references('id_facultad')->on('facultad');
            $table->unique(['fk_facultad', 'nombre']);
        });
        foreach (['ciclo', 'subjects', 'usuario'] as $dependentTable) {
            Schema::create($dependentTable, function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('fk_carrera');
                $table->foreign('fk_carrera')->references('id_carrera')->on('carrera')->restrictOnDelete();
            });
        }
        DB::table('facultad')->insert([
            ['id_facultad' => 1, 'nombre' => 'Ingeniería'],
            ['id_facultad' => 2, 'nombre' => 'Educación'],
            ['id_facultad' => 4, 'nombre' => 'Jurisprudencia'],
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('career_name_migration_test');
        DB::setDefaultConnection($this->originalConnection);

        parent::tearDown();
    }

    public function test_duplicate_careers_are_disambiguated_without_losing_ids_states_timestamps_or_references(): void
    {
        $this->insertCareer(5, 2, 'Software');
        $this->insertCareer(4, 4, 'Software', false);
        $this->insertCareer(1, 1, 'Software');
        $this->insertCareer(7, 1, 'Medicina');
        foreach (['ciclo', 'subjects', 'usuario'] as $dependentTable) {
            foreach ([1, 4, 5, 7] as $careerId) {
                DB::table($dependentTable)->insert(['fk_carrera' => $careerId]);
            }
        }
        $before = $this->careerMetadata();
        $references = $this->dependentReferences();

        $migration = require database_path('migrations/2026_10_02_162220_make_career_name_unique_across_all_faculties.php');
        $migration->up();

        $this->assertSame([
            1 => 'Software', 4 => 'Software (Jurisprudencia)', 5 => 'Software (Educación)', 7 => 'Medicina',
        ], DB::table('carrera')->orderBy('id_carrera')->pluck('nombre', 'id_carrera')->all());
        $this->assertSame($before, $this->careerMetadata());
        $this->assertSame($references, $this->dependentReferences());

        $afterFirstRun = DB::table('carrera')->orderBy('id_carrera')->get()->toJson();
        $migration->up();
        $this->assertSame($afterFirstRun, DB::table('carrera')->orderBy('id_carrera')->get()->toJson());

        $this->expectException(QueryException::class);
        $this->insertCareer(8, 2, 'Software');
    }

    public function test_existing_unique_names_are_preserved_when_generated_names_collide(): void
    {
        $this->insertCareer(1, 1, 'Software');
        $this->insertCareer(4, 4, 'Software');
        $this->insertCareer(7, 2, 'Software (Jurisprudencia)');
        $this->insertCareer(8, 2, 'Software (Jurisprudencia) [4]');

        $migration = require database_path('migrations/2026_10_02_162220_make_career_name_unique_across_all_faculties.php');
        $migration->up();

        $this->assertSame([
            1 => 'Software',
            4 => 'Software (Jurisprudencia) [4-2]',
            7 => 'Software (Jurisprudencia)',
            8 => 'Software (Jurisprudencia) [4]',
        ], DB::table('carrera')->orderBy('id_carrera')->pluck('nombre', 'id_carrera')->all());
    }

    public function test_long_unicode_names_stay_within_the_column_limit_and_keep_valid_text(): void
    {
        $originalName = str_repeat('Á', 150);
        DB::table('facultad')->where('id_facultad', 4)->update(['nombre' => str_repeat('É', 150)]);
        $this->insertCareer(1, 1, $originalName);
        $this->insertCareer(4, 4, $originalName);

        $migration = require database_path('migrations/2026_10_02_162220_make_career_name_unique_across_all_faculties.php');
        $migration->up();

        $renamed = DB::table('carrera')->where('id_carrera', 4)->value('nombre');
        $this->assertSame($originalName, DB::table('carrera')->where('id_carrera', 1)->value('nombre'));
        $this->assertTrue(mb_check_encoding($renamed, 'UTF-8'));
        $this->assertLessThanOrEqual(150, mb_strlen($renamed, 'UTF-8'));
        $this->assertStringStartsWith('Á (É', $renamed);
        $this->assertStringEndsWith(')', $renamed);
        $this->assertNotSame($originalName, $renamed);
    }

    private function insertCareer(int $id, int $facultyId, string $name, bool $active = true): void
    {
        DB::table('carrera')->insert([
            'id_carrera' => $id, 'fk_facultad' => $facultyId, 'nombre' => $name, 'estado' => $active,
            'created_at' => '2026-09-27 12:00:00', 'updated_at' => '2026-10-02 10:00:00',
        ]);
    }

    private function careerMetadata(): string
    {
        return DB::table('carrera')->orderBy('id_carrera')
            ->get(['id_carrera', 'fk_facultad', 'estado', 'created_at', 'updated_at'])->toJson();
    }

    private function dependentReferences(): array
    {
        $references = [];

        foreach (['ciclo', 'subjects', 'usuario'] as $dependentTable) {
            $references[$dependentTable] = DB::table($dependentTable)->orderBy('id')->get()->toJson();
        }

        return $references;
    }
}

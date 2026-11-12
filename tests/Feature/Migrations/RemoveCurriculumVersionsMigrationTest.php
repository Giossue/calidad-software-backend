<?php

namespace Tests\Feature\Migrations;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Sin versiones de la malla: la migración se niega a correr si alguna carrera
 * tiene una malla además de la inicial, para no perder datos en silencio.
 */
class RemoveCurriculumVersionsMigrationTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = DB::getDefaultConnection();
        config(['database.connections.curriculum_versions_migration_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::setDefaultConnection('curriculum_versions_migration_test');
        Schema::create('carrera', function (Blueprint $table): void {
            $table->increments('id_carrera');
            $table->string('nombre', 150);
        });
        Schema::create('periodo_academico', function (Blueprint $table): void {
            $table->increments('id_periodo');
        });
        Schema::create('usuario', function (Blueprint $table): void {
            $table->increments('id_usuario');
        });
        Schema::create('subjects', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('career_id');
            $table->string('code', 30)->nullable();
            $table->string('name', 150);
            $table->foreign('career_id')->references('id_carrera')->on('carrera');
            $table->unique(['career_id', 'code']);
        });
        DB::table('carrera')->insert([['id_carrera' => 1, 'nombre' => 'Software'], ['id_carrera' => 2, 'nombre' => 'TI']]);
        DB::table('periodo_academico')->insert(['id_periodo' => 7]);
        DB::table('usuario')->insert([['id_usuario' => 1], ['id_usuario' => 2]]);
        DB::table('subjects')->insert([
            ['id' => 1, 'career_id' => 1, 'code' => 'SW-1', 'name' => 'ALGORITMOS'],
            ['id' => 2, 'career_id' => 2, 'code' => 'TI-1', 'name' => 'REDES'],
        ]);
        $previous = require database_path('migrations/2026_11_10_100000_create_curricula_table.php');
        $previous->up();
    }

    protected function tearDown(): void
    {
        DB::purge('curriculum_versions_migration_test');
        DB::setDefaultConnection($this->originalConnection);

        parent::tearDown();
    }

    public function test_it_refuses_to_run_when_a_career_has_more_than_its_initial_malla(): void
    {
        DB::table('curricula')->insert(['id' => 9, 'career_id' => 1, 'name' => 'Malla 2027', 'from_period_id' => 7, 'up_to_cycle' => 2]);
        DB::table('subjects')->insert(['id' => 3, 'career_id' => 1, 'curriculum_id' => 9, 'code' => 'SW-1', 'name' => 'ALGORITMOS']);
        DB::table('usuario')->where('id_usuario', 1)->update(['curriculum_id' => 9]);

        try {
            $migration = require database_path('migrations/2026_11_11_110000_remove_curriculum_versions.php');
            $migration->up();
            $this->fail('La migración borró una malla que no es la inicial.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Software', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasTable('curricula'));
        $this->assertSame(3, DB::table('subjects')->whereNotNull('curriculum_id')->count());
        $this->assertSame(9, (int) DB::table('usuario')->where('id_usuario', 1)->value('curriculum_id'));
    }

    public function test_it_removes_the_versions_and_restores_the_unique_code_per_career(): void
    {
        DB::table('usuario')->where('id_usuario', 1)->update(['curriculum_id' => DB::table('curricula')->where('career_id', 1)->value('id')]);
        $migration = require database_path('migrations/2026_11_11_110000_remove_curriculum_versions.php');

        $migration->up();

        $this->assertFalse(Schema::hasTable('curricula'));
        $this->assertFalse(Schema::hasColumn('subjects', 'curriculum_id'));
        $this->assertFalse(Schema::hasColumn('usuario', 'curriculum_id'));
        $this->assertSame(['ALGORITMOS', 'REDES'], DB::table('subjects')->orderBy('id')->pluck('name')->all());
        try {
            DB::table('subjects')->insert(['career_id' => 1, 'code' => 'SW-1', 'name' => 'OTRA']);
            $this->fail('Se repitió un código en la carrera.');
        } catch (QueryException) {
            $this->assertSame(2, DB::table('subjects')->count());
        }

        $migration->down();

        $this->assertSame(2, DB::table('curricula')->whereNull('from_period_id')->where('name', 'Malla inicial')->count());
        $this->assertSame(0, DB::table('subjects')->whereNull('curriculum_id')->count());
        $this->assertTrue(Schema::hasColumn('usuario', 'curriculum_id'));
    }
}

<?php

namespace Tests\Feature\Migrations;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Toda asignatura tiene modalidad: la migración la toma de su carrera y se
 * niega a correr si alguna carrera no tiene modalidad, sin inventar un valor.
 */
class RequireSubjectModalityMigrationTest extends TestCase
{
    private const MIGRATION = 'migrations/2026_11_12_130000_require_subject_modality.php';

    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = DB::getDefaultConnection();
        config(['database.connections.subject_modality_migration_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::setDefaultConnection('subject_modality_migration_test');
        Schema::create('modalidad', function (Blueprint $table): void {
            $table->increments('id_modalidad');
            $table->string('nombre', 100);
        });
        Schema::create('carrera', function (Blueprint $table): void {
            $table->increments('id_carrera');
            $table->string('nombre', 150);
            $table->unsignedInteger('fk_modalidad')->nullable();
        });
        Schema::create('subjects', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('career_id');
            $table->string('name', 150);
            $table->unsignedInteger('modality_id')->nullable();
            $table->foreign('career_id')->references('id_carrera')->on('carrera');
            $table->foreign('modality_id')->references('id_modalidad')->on('modalidad')->nullOnDelete();
        });
        DB::table('modalidad')->insert([['id_modalidad' => 1, 'nombre' => 'Presencial'], ['id_modalidad' => 2, 'nombre' => 'Virtual'], ['id_modalidad' => 3, 'nombre' => 'Híbrida']]);
        DB::table('carrera')->insert([
            ['id_carrera' => 1, 'nombre' => 'Software', 'fk_modalidad' => 1],
            ['id_carrera' => 2, 'nombre' => 'TI', 'fk_modalidad' => 2],
        ]);
        DB::table('subjects')->insert([
            ['id' => 1, 'career_id' => 1, 'name' => 'ALGORITMOS', 'modality_id' => null],
            ['id' => 2, 'career_id' => 2, 'name' => 'REDES', 'modality_id' => null],
            ['id' => 3, 'career_id' => 1, 'name' => 'FISICA', 'modality_id' => 3],
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('subject_modality_migration_test');
        DB::setDefaultConnection($this->originalConnection);

        parent::tearDown();
    }

    public function test_it_takes_the_modality_of_the_career_and_makes_it_required(): void
    {
        $migration = require database_path(self::MIGRATION);

        $migration->up();

        $this->assertSame([1 => 1, 2 => 2, 3 => 3], DB::table('subjects')->orderBy('id')->pluck('modality_id', 'id')->map(fn ($id): int => (int) $id)->all());
        try {
            DB::table('subjects')->insert(['career_id' => 1, 'name' => 'SIN MODALIDAD', 'modality_id' => null]);
            $this->fail('Se registró una asignatura sin modalidad.');
        } catch (QueryException) {
            $this->assertSame(3, DB::table('subjects')->count());
        }
        try {
            DB::table('modalidad')->where('id_modalidad', 3)->delete();
            $this->fail('Se borró una modalidad que usan asignaturas.');
        } catch (QueryException) {
            $this->assertSame(3, (int) DB::table('subjects')->where('id', 3)->value('modality_id'));
        }
    }

    public function test_it_refuses_to_run_when_a_career_has_no_modality(): void
    {
        DB::table('carrera')->insert(['id_carrera' => 3, 'nombre' => 'Medicina', 'fk_modalidad' => null]);
        DB::table('subjects')->insert(['id' => 4, 'career_id' => 3, 'name' => 'ANATOMIA', 'modality_id' => null]);

        try {
            $migration = require database_path(self::MIGRATION);
            $migration->up();
            $this->fail('La migración inventó una modalidad.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Medicina', $exception->getMessage());
            $this->assertStringNotContainsString('Software', $exception->getMessage());
        }

        $this->assertNull(DB::table('subjects')->where('id', 4)->value('modality_id'));
        DB::table('subjects')->insert(['career_id' => 1, 'name' => 'AUN OPCIONAL', 'modality_id' => null]);
        $this->assertSame(1, DB::table('subjects')->where('name', 'AUN OPCIONAL')->count());
    }

    public function test_down_makes_the_modality_optional_again(): void
    {
        $migration = require database_path(self::MIGRATION);
        $migration->up();

        $migration->down();

        DB::table('subjects')->insert(['id' => 5, 'career_id' => 1, 'name' => 'OPCIONAL', 'modality_id' => null]);
        DB::table('modalidad')->where('id_modalidad', 3)->delete();
        $this->assertNull(DB::table('subjects')->where('id', 3)->value('modality_id'));
        $this->assertNull(DB::table('subjects')->where('id', 5)->value('modality_id'));
    }
}

<?php

namespace Tests\Feature\Migrations;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Varias mallas por carrera: cada carrera existente recibe una malla vigente
 * «Malla inicial» con sus asignaturas y sus estudiantes; `down()` solo revierte
 * mientras cada carrera tenga una sola malla.
 */
class CreateMallaMigrationTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = DB::getDefaultConnection();
        config(['database.connections.malla_migration_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::setDefaultConnection('malla_migration_test');
        Schema::create('carrera', function (Blueprint $table): void {
            $table->increments('id_carrera');
            $table->string('nombre', 150);
        });
        Schema::create('roles', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('slug', 50);
        });
        Schema::create('usuario', function (Blueprint $table): void {
            $table->increments('id_usuario');
            $table->unsignedInteger('fk_carrera')->nullable();
        });
        Schema::create('role_user', function (Blueprint $table): void {
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('role_id');
        });
        Schema::create('subjects', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('career_id');
            $table->string('code', 30)->nullable();
            $table->string('name', 150);
            $table->foreign('career_id')->references('id_carrera')->on('carrera');
            $table->unique(['career_id', 'code']);
        });
        DB::table('carrera')->insert([['id_carrera' => 1, 'nombre' => 'Software'], ['id_carrera' => 2, 'nombre' => 'TI'], ['id_carrera' => 3, 'nombre' => 'Nueva']]);
        DB::table('roles')->insert([['id' => 1, 'slug' => 'estudiante'], ['id' => 2, 'slug' => 'docente']]);
        DB::table('usuario')->insert([
            ['id_usuario' => 1, 'fk_carrera' => 1], // estudiante de Software
            ['id_usuario' => 2, 'fk_carrera' => 2], // estudiante de TI
            ['id_usuario' => 3, 'fk_carrera' => 1], // docente de Software
            ['id_usuario' => 4, 'fk_carrera' => null], // estudiante sin carrera
        ]);
        DB::table('role_user')->insert([
            ['user_id' => 1, 'role_id' => 1], ['user_id' => 2, 'role_id' => 1], ['user_id' => 3, 'role_id' => 2], ['user_id' => 4, 'role_id' => 1],
        ]);
        DB::table('subjects')->insert([
            ['id' => 1, 'career_id' => 1, 'code' => 'SW-1', 'name' => 'ALGORITMOS'],
            ['id' => 2, 'career_id' => 1, 'code' => 'SW-2', 'name' => 'REDES'],
            ['id' => 3, 'career_id' => 2, 'code' => 'TI-1', 'name' => 'REDES'],
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('malla_migration_test');
        DB::setDefaultConnection($this->originalConnection);

        parent::tearDown();
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_11_15_110000_create_malla_table.php');
    }

    public function test_each_career_gets_a_current_initial_malla_with_its_subjects_and_students(): void
    {
        $this->migration()->up();

        $mallas = DB::table('malla')->orderBy('career_id')->get();
        $this->assertSame([1, 2, 3], $mallas->pluck('career_id')->map(fn ($id): int => (int) $id)->all());
        $this->assertSame(['Malla inicial'], $mallas->pluck('name')->unique()->values()->all());
        $this->assertSame(3, $mallas->where('is_current', true)->count());
        $software = (int) $mallas->firstWhere('career_id', 1)->id;
        $ti = (int) $mallas->firstWhere('career_id', 2)->id;

        $this->assertSame([1 => $software, 2 => $software, 3 => $ti], DB::table('subjects')->orderBy('id')->pluck('malla_id', 'id')->map(fn ($id): int => (int) $id)->all());
        $this->assertSame(['ALGORITMOS', 'REDES', 'REDES'], DB::table('subjects')->orderBy('id')->pluck('name')->all());
        $this->assertSame($software, (int) DB::table('usuario')->where('id_usuario', 1)->value('malla_id'));
        $this->assertSame($ti, (int) DB::table('usuario')->where('id_usuario', 2)->value('malla_id'));
        $this->assertNull(DB::table('usuario')->where('id_usuario', 3)->value('malla_id'), 'Un docente no sigue una malla.');
        $this->assertNull(DB::table('usuario')->where('id_usuario', 4)->value('malla_id'), 'Sin carrera no hay malla.');
    }

    public function test_a_code_repeats_across_mallas_of_one_career_but_not_inside_one_malla(): void
    {
        $this->migration()->up();
        $second = DB::table('malla')->insertGetId(['career_id' => 1, 'name' => 'Malla 2027', 'is_current' => false]);

        DB::table('subjects')->insert(['career_id' => 1, 'malla_id' => $second, 'code' => 'SW-1', 'name' => 'ALGORITMOS']);
        $this->assertSame(2, DB::table('subjects')->where('code', 'SW-1')->count());

        try {
            DB::table('subjects')->insert(['career_id' => 1, 'malla_id' => $second, 'code' => 'SW-1', 'name' => 'OTRA']);
            $this->fail('Se repitió un código dentro de la malla.');
        } catch (QueryException) {
            $this->assertSame(2, DB::table('subjects')->where('code', 'SW-1')->count());
        }
    }

    public function test_a_subject_requires_a_malla_and_a_career_has_one_current_malla(): void
    {
        $this->migration()->up();

        try {
            DB::table('subjects')->insert(['career_id' => 1, 'code' => 'SW-9', 'name' => 'SIN MALLA']);
            $this->fail('Se guardó una asignatura sin malla.');
        } catch (QueryException) {
            $this->assertSame(0, DB::table('subjects')->where('code', 'SW-9')->count());
        }

        try {
            DB::table('malla')->insert(['career_id' => 1, 'name' => 'Malla 2027', 'is_current' => true]);
            $this->fail('La carrera quedó con dos mallas vigentes.');
        } catch (QueryException) {
            $this->assertSame(1, DB::table('malla')->where('career_id', 1)->count());
        }
    }

    public function test_rollback_refuses_while_a_career_has_more_than_one_malla(): void
    {
        $migration = $this->migration();
        $migration->up();
        DB::table('malla')->insert(['career_id' => 1, 'name' => 'Malla 2027', 'is_current' => false]);

        try {
            $migration->down();
            $this->fail('La reversión borró una malla.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Software', $exception->getMessage());
            $this->assertStringNotContainsString('TI', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasTable('malla'));
        $this->assertSame(4, DB::table('malla')->count());
        $this->assertSame(3, DB::table('subjects')->whereNotNull('malla_id')->count());
    }

    public function test_rollback_restores_the_unique_code_per_career_when_each_career_has_one_malla(): void
    {
        $migration = $this->migration();
        $migration->up();

        $migration->down();

        $this->assertFalse(Schema::hasTable('malla'));
        $this->assertFalse(Schema::hasColumn('subjects', 'malla_id'));
        $this->assertFalse(Schema::hasColumn('usuario', 'malla_id'));
        $this->assertSame(['ALGORITMOS', 'REDES', 'REDES'], DB::table('subjects')->orderBy('id')->pluck('name')->all());
        $this->expectException(QueryException::class);
        DB::table('subjects')->insert(['career_id' => 1, 'code' => 'SW-1', 'name' => 'OTRA']);
    }
}

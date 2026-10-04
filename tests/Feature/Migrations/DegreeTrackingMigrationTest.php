<?php

namespace Tests\Feature\Migrations;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class DegreeTrackingMigrationTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = DB::getDefaultConnection();
        config(['database.connections.tracking_baseline_fixture' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true,
        ]]);
        DB::setDefaultConnection('tracking_baseline_fixture');
        Schema::clearResolvedInstance('db.schema');
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        Schema::create('usuario', fn (Blueprint $table) => $table->increments('id_usuario'));
        Schema::create('tema_titulacion', fn (Blueprint $table) => $table->increments('id_tema_tit'));
        DB::table('usuario')->insert(['id_usuario' => 1]);
        DB::table('tema_titulacion')->insert(['id_tema_tit' => 1]);
    }

    protected function tearDown(): void
    {
        DB::purge('tracking_baseline_fixture');
        DB::setDefaultConnection($this->originalConnection);
        Schema::clearResolvedInstance('db.schema');

        parent::tearDown();
    }

    public function test_existing_records_constraints_and_triggers_survive_repeated_migration(): void
    {
        $migration = $this->migration();
        $migration->up();
        $this->insertTrackingHistory();
        DB::statement(<<<'SQL'
            CREATE TRIGGER historical_tracking_guard
            BEFORE DELETE ON informe_titulacion
            BEGIN SELECT RAISE(ABORT, 'Preservar informe histórico'); END
            SQL);
        $before = [];
        foreach (['ficha_seguimiento', 'actividad_avance', 'informe_titulacion'] as $table) {
            $before[$table] = DB::table($table)->first();
        }
        $schemaBefore = DB::select("SELECT name, sql FROM sqlite_master WHERE tbl_name IN ('ficha_seguimiento', 'actividad_avance', 'informe_titulacion') ORDER BY name");

        $migration->up();
        $migration->up();

        foreach ($before as $table => $record) {
            $this->assertEquals($record, DB::table($table)->first());
        }
        $this->assertEquals($schemaBefore, DB::select("SELECT name, sql FROM sqlite_master WHERE tbl_name IN ('ficha_seguimiento', 'actividad_avance', 'informe_titulacion') ORDER BY name"));
        $this->expectException(QueryException::class);
        DB::table('informe_titulacion')->delete();
    }

    public function test_missing_children_are_created_without_changing_existing_tracking_sheet(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE ficha_seguimiento (
                id_ficha INTEGER PRIMARY KEY,
                fk_tema_tit INTEGER NOT NULL UNIQUE,
                historical_note TEXT,
                FOREIGN KEY (fk_tema_tit) REFERENCES tema_titulacion(id_tema_tit) ON DELETE RESTRICT
            )
            SQL);
        DB::table('ficha_seguimiento')->insert([
            'id_ficha' => 7, 'fk_tema_tit' => 1, 'historical_note' => 'Registro previo',
        ]);
        $before = DB::table('ficha_seguimiento')->first();
        $schemaBefore = DB::selectOne("SELECT sql FROM sqlite_master WHERE name = 'ficha_seguimiento'")->sql;

        $this->migration()->up();

        $this->assertEquals($before, DB::table('ficha_seguimiento')->first());
        $this->assertSame($schemaBefore, DB::selectOne("SELECT sql FROM sqlite_master WHERE name = 'ficha_seguimiento'")->sql);
        DB::table('actividad_avance')->insert([
            'fk_ficha' => 7, 'fk_docente' => 1, 'descripcion' => str_repeat('á', 1000),
        ]);
        DB::table('informe_titulacion')->insert([
            'fk_ficha' => 7, 'fk_coord_tit' => 1, 'observaciones_finales' => str_repeat('á', 1000),
        ]);
        $this->assertSame(str_repeat('á', 1000), DB::table('actividad_avance')->value('descripcion'));
        $this->assertSame(str_repeat('á', 1000), DB::table('informe_titulacion')->value('observaciones_finales'));
    }

    #[DataProvider('invalidTrackingRecords')]
    public function test_fresh_tables_enforce_tracking_invariants(string $table, array $record): void
    {
        $this->migration()->up();
        DB::table('ficha_seguimiento')->insert(['fk_tema_tit' => 1]);
        DB::table('tema_titulacion')->insert(['id_tema_tit' => 2]);

        $this->expectException(QueryException::class);
        DB::table($table)->insert($record);
    }

    public static function invalidTrackingRecords(): array
    {
        return [
            'duplicate topic' => ['ficha_seguimiento', ['fk_tema_tit' => 1]],
            'negative progress' => ['ficha_seguimiento', ['fk_tema_tit' => 2, 'porcentaje_avance' => -0.01]],
            'excess progress' => ['ficha_seguimiento', ['fk_tema_tit' => 2, 'porcentaje_avance' => 100.01]],
            'blank status' => ['ficha_seguimiento', ['fk_tema_tit' => 2, 'estado' => '   ']],
            'blank activity' => ['actividad_avance', ['fk_ficha' => 1, 'fk_docente' => 1, 'descripcion' => '   ']],
            'missing teacher' => ['actividad_avance', ['fk_ficha' => 1, 'fk_docente' => null, 'descripcion' => 'Avance']],
            'missing coordinator' => ['informe_titulacion', ['fk_ficha' => 1, 'fk_coord_tit' => null]],
        ];
    }

    #[DataProvider('protectedHistoricalParents')]
    public function test_parent_deletions_preserve_tracking_history(string $table): void
    {
        $this->migration()->up();
        $this->insertTrackingHistory();

        $this->expectException(QueryException::class);
        DB::table($table)->delete();
    }

    public static function protectedHistoricalParents(): array
    {
        return [['usuario'], ['tema_titulacion'], ['ficha_seguimiento']];
    }

    public function test_automatic_rollback_is_rejected_to_preserve_the_institutional_baseline(): void
    {
        $this->migration()->up();
        $this->insertTrackingHistory();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no admite rollback automático');

        $this->migration()->down();
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_10_03_144624_create_ficha_seguimiento_and_actividad_avance_and_informe_titulacion_tables.php');
    }

    private function insertTrackingHistory(): void
    {
        DB::table('ficha_seguimiento')->insert(['fk_tema_tit' => 1, 'porcentaje_avance' => 25]);
        DB::table('actividad_avance')->insert([
            'fk_ficha' => 1, 'fk_docente' => 1, 'descripcion' => 'Avance histórico',
        ]);
        DB::table('informe_titulacion')->insert([
            'fk_ficha' => 1, 'fk_coord_tit' => 1, 'observaciones_finales' => 'Informe histórico',
        ]);
    }
}

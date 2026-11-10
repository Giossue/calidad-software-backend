<?php

namespace Tests\Feature\Migrations;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Al proponer una alternativa, la propuesta pendiente pasa a `descartado`. Las
 * restricciones de PostgreSQL deben aceptarlo (sin revisor ni fecha de revisión).
 */
class DiscardedDegreeTopicStatusMigrationTest extends TestCase
{
    private bool $isolatedSchemaStarted = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Las restricciones de estado de titulación requieren PostgreSQL.');
        }

        DB::beginTransaction();
        $this->isolatedSchemaStarted = true;
        $schema = 'discarded_topic_test_'.bin2hex(random_bytes(6));
        DB::statement('CREATE SCHEMA "'.$schema.'"');
        DB::statement('SET LOCAL search_path TO "'.$schema.'"');
        DB::statement("CREATE TABLE tema_titulacion (id bigint PRIMARY KEY, estado varchar(50) NOT NULL DEFAULT 'pendiente', fk_coord_revisor bigint, fecha_revision timestamp)");
        // Restricciones de la línea base (2026_09_27_010000).
        DB::statement("ALTER TABLE tema_titulacion ADD CONSTRAINT degree_topic_status_valid CHECK (estado IN ('pendiente', 'aprobado', 'rechazado'))");
        DB::statement(<<<'SQL'
            ALTER TABLE tema_titulacion ADD CONSTRAINT degree_topic_review_matches_status CHECK (
                (estado = 'pendiente' AND fk_coord_revisor IS NULL AND fecha_revision IS NULL)
                OR (estado IN ('aprobado', 'rechazado') AND fk_coord_revisor IS NOT NULL AND fecha_revision IS NOT NULL)
            )
            SQL);

        $migration = require database_path('migrations/2026_11_10_110000_allow_discarded_degree_topic_status.php');
        $migration->up();
    }

    protected function tearDown(): void
    {
        if ($this->isolatedSchemaStarted) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_a_pending_topic_can_be_discarded_when_the_student_proposes_an_alternative(): void
    {
        DB::table('tema_titulacion')->insert(['id' => 1, 'estado' => 'pendiente']);

        $this->assertSame(1, DB::table('tema_titulacion')->where('id', 1)->update(['estado' => 'descartado']));
        $this->assertSame('descartado', DB::table('tema_titulacion')->where('id', 1)->value('estado'));
    }

    public function test_a_discarded_topic_cannot_carry_a_review(): void
    {
        $this->expectException(QueryException::class);

        DB::table('tema_titulacion')->insert(['id' => 2, 'estado' => 'descartado', 'fk_coord_revisor' => 9, 'fecha_revision' => now()]);
    }

    public function test_unknown_states_and_reviewed_states_without_reviewer_are_still_rejected(): void
    {
        foreach ([
            ['id' => 3, 'estado' => 'otro'],
            ['id' => 4, 'estado' => 'aprobado'],
        ] as $row) {
            DB::statement('SAVEPOINT attempt');
            try {
                DB::table('tema_titulacion')->insert($row);
                $this->fail('Se aceptó un estado inválido: '.$row['estado']);
            } catch (QueryException) {
                DB::statement('ROLLBACK TO SAVEPOINT attempt');
            }
        }

        $this->assertSame(0, DB::table('tema_titulacion')->count());
    }
}

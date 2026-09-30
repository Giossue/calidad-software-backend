<?php

namespace Tests\Feature\Api\V1\Teacher;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TeacherSchemaMigrationTest extends TestCase
{
    public function test_expanding_existing_domain_tables_preserves_history_and_protects_classification_uniqueness(): void
    {
        $original = DB::getDefaultConnection();
        config(['database.connections.teacher_baseline_fixture' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
        DB::setDefaultConnection('teacher_baseline_fixture');
        Schema::clearResolvedInstance('db.schema');
        try {
            $this->assertSame('sqlite', DB::connection()->getDriverName());
            $this->assertSame(':memory:', DB::connection()->getDatabaseName());
            // A representative legacy schema, before any teacher extensions.
            foreach (['usuario' => 'id_usuario', 'asignatura_tutoria' => 'id_asig_tutoria', 'inscripcion_tutoria' => 'id_inscripcion'] as $table => $id) {
                Schema::create($table, fn (Blueprint $blueprint) => $blueprint->increments($id));
                DB::table($table)->insert([$id => 1]);
            }
            foreach (['tema' => 'id_tema', 'actividad' => 'id_actividad', 'metodologia' => 'id_metodologia'] as $table => $id) {
                Schema::create($table, function (Blueprint $blueprint) use ($id): void {
                    $blueprint->increments($id);
                    $blueprint->string('descripcion');
                    $blueprint->boolean('estado');
                });
                DB::table($table)->insert([$id => 1, 'descripcion' => 'Contenido histórico', 'estado' => false]);
            }
            Schema::create('nota', function (Blueprint $table): void {
                $table->increments('id_nota');
                $table->unsignedInteger('fk_inscripcion');
                $table->string('tipo');
                $table->decimal('valor', 10, 2);
                $table->date('fecha_registro');
            });
            DB::table('nota')->insert(['id_nota' => 1, 'fk_inscripcion' => 1, 'tipo' => 'diagnostico', 'valor' => '8.25', 'fecha_registro' => '2026-09-28']);
            Schema::create('metrica_conocimiento', function (Blueprint $table): void {
                $table->increments('id_metrica');
                $table->unsignedInteger('fk_id_usuario');
                $table->string('descripcion');
            });
            DB::table('metrica_conocimiento')->insert(['id_metrica' => 1, 'fk_id_usuario' => 1, 'descripcion' => 'Métrica histórica']);
            Schema::create('asistencia', function (Blueprint $table): void {
                $table->increments('id_asistencia');
                $table->unsignedInteger('fk_inscripcion');
                $table->date('fecha');
                $table->boolean('estado_asistencia');
            });
            DB::table('asistencia')->insert(['id_asistencia' => 1, 'fk_inscripcion' => 1, 'fecha' => '2026-09-28', 'estado_asistencia' => true]);
            Schema::create('reporte', function (Blueprint $table): void {
                $table->increments('id_reporte');
                $table->text('content');
            });
            DB::table('reporte')->insert(['id_reporte' => 1, 'content' => 'Informe histórico']);
            $before = [];
            foreach (['tema', 'actividad', 'metodologia', 'nota', 'metrica_conocimiento', 'asistencia', 'reporte'] as $table) {
                $before[$table] = (array) DB::table($table)->first();
            }
            $migration = require database_path('migrations/2026_09_29_000000_create_teacher_module_schema.php');
            $migration->up();
            foreach ($before as $table => $record) {
                $this->assertEquals($record, array_intersect_key((array) DB::table($table)->first(), $record));
            }
            $this->assertTrue(Schema::hasColumn('reporte', 'summary'));
            $this->assertTrue(Schema::hasColumn('asistencia', 'session_id'));
            $this->assertTrue(Schema::hasTable('tutoring_sessions'));
            DB::table('metrica_conocimiento')->where('id_metrica', 1)->update(['enrollment_id' => 1]);
            $this->expectException(QueryException::class);
            DB::table('metrica_conocimiento')->insert(['fk_id_usuario' => 1, 'descripcion' => 'Duplicada', 'enrollment_id' => 1]);
        } finally {
            DB::purge('teacher_baseline_fixture');
            DB::setDefaultConnection($original);
            Schema::clearResolvedInstance('db.schema');
        }
    }
}

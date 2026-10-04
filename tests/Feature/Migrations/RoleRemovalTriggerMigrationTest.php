<?php

namespace Tests\Feature\Migrations;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoleRemovalTriggerMigrationTest extends TestCase
{
    private bool $isolatedSchemaStarted = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('El trigger de bajas de roles requiere PostgreSQL.');
        }

        DB::beginTransaction();
        $this->isolatedSchemaStarted = true;
        $schema = 'role_removal_test_'.bin2hex(random_bytes(6));
        DB::statement('CREATE SCHEMA "'.$schema.'"');
        DB::statement('SET LOCAL search_path TO "'.$schema.'"');
        DB::statement('CREATE TABLE roles (id bigint PRIMARY KEY, slug text NOT NULL)');
        DB::statement('CREATE TABLE role_user (user_id bigint, role_id bigint)');
        DB::statement('CREATE TABLE usuario_paralelo (fk_usuario bigint)');
        DB::statement('CREATE TABLE inscripcion_tutoria (fk_id_usuario bigint)');
        DB::statement('CREATE TABLE tema_titulacion (fk_id_usuario bigint, fk_coord_revisor bigint)');
        DB::statement('CREATE TABLE asignatura_tutoria (fk_docente bigint)');
        DB::statement('CREATE TABLE asignacion_docente (fk_id_usuario bigint)');
        DB::statement('CREATE TABLE observacion_titulacion (fk_coord_tit bigint)');
        DB::table('roles')->insert([
            ['id' => 1, 'slug' => 'estudiante'],
            ['id' => 2, 'slug' => 'docente'],
            ['id' => 3, 'slug' => 'coordinador_titulacion'],
        ]);

        $migration = require database_path('migrations/2026_10_02_172000_fix_fn_validar_baja_rol_usuario_trigger.php');
        $migration->up();
        DB::statement('CREATE TRIGGER validar_baja_rol_usuario BEFORE DELETE ON role_user FOR EACH ROW EXECUTE FUNCTION fn_validar_baja_rol_usuario()');
    }

    protected function tearDown(): void
    {
        if ($this->isolatedSchemaStarted) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_absent_optional_tables_allow_removing_unreferenced_roles_and_preserve_core_rules(): void
    {
        foreach ([1, 2, 3] as $roleId) {
            DB::table('role_user')->insert(['user_id' => 10 + $roleId, 'role_id' => $roleId]);
            $this->assertSame(1, DB::table('role_user')->where('user_id', 10 + $roleId)->delete());
        }

        $dependencies = [
            [1, 'usuario_paralelo', 'fk_usuario', 'estudiante'],
            [1, 'inscripcion_tutoria', 'fk_id_usuario', 'estudiante'],
            [1, 'tema_titulacion', 'fk_id_usuario', 'estudiante'],
            [2, 'asignatura_tutoria', 'fk_docente', 'docente'],
            [2, 'asignacion_docente', 'fk_id_usuario', 'docente'],
            [3, 'tema_titulacion', 'fk_coord_revisor', 'coordinador de titulación'],
            [3, 'observacion_titulacion', 'fk_coord_tit', 'coordinador de titulación'],
        ];

        foreach ($dependencies as $index => [$roleId, $table, $column, $roleName]) {
            $this->assertDependencyPreventsRoleRemoval(100 + $index, $roleId, $table, $column, $roleName);
        }
    }

    public function test_tables_created_after_installing_the_trigger_immediately_protect_role_removal(): void
    {
        // Ejecutar el trigger antes de crear las tablas reproduce una instalación
        // nueva: no puede depender de los planes compilados con tablas previas.
        DB::table('role_user')->insert([
            ['user_id' => 20, 'role_id' => 2],
            ['user_id' => 21, 'role_id' => 3],
        ]);
        $this->assertSame(2, DB::table('role_user')->delete());

        $dependencies = [
            [2, 'observacion', 'fk_docente', 'docente'],
            [2, 'actividad_avance', 'fk_docente', 'docente'],
            [3, 'horario_titulacion', 'fk_coord_tit', 'coordinador de titulación'],
            [3, 'informe_titulacion', 'fk_coord_tit', 'coordinador de titulación'],
        ];

        foreach ($dependencies as $index => [$roleId, $table, $column, $roleName]) {
            DB::statement('CREATE TABLE '.$table.' ('.$column.' bigint)');
            $this->assertDependencyPreventsRoleRemoval(200 + $index, $roleId, $table, $column, $roleName);
        }
    }

    private function assertDependencyPreventsRoleRemoval(int $userId, int $roleId, string $table, string $column, string $roleName): void
    {
        DB::table('role_user')->insert(['user_id' => $userId, 'role_id' => $roleId]);
        DB::table($table)->insert([$column => $userId]);

        try {
            // El savepoint restaura la transacción PostgreSQL tras el rechazo.
            DB::transaction(fn () => DB::table('role_user')->where('user_id', $userId)->delete());
            $this->fail('Se permitió retirar un rol usado en '.$table.'.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('No se puede quitar el rol '.$roleName, $exception->getMessage());
        }

        $this->assertSame(1, DB::table('role_user')->where('user_id', $userId)->count());
        DB::table($table)->where($column, $userId)->delete();
        $this->assertSame(1, DB::table('role_user')->where('user_id', $userId)->delete());
    }
}

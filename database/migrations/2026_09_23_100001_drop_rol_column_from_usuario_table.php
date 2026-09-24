<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * fn_validar_cambio_rol_usuario/validar_cambio_rol_usuario existían directo en
     * PostgreSQL (no versionados en ninguna migración) y bloqueaban UPDATE OF rol
     * cuando el usuario aún tenía registros ligados a su rol anterior. Como el rol
     * ahora se gestiona en role_user, la regla se traslada a un trigger BEFORE
     * DELETE en esa tabla: soltar una fila de role_user es el equivalente exacto
     * a "cambiar de rol" en el modelo anterior.
     */
    private const NEW_FUNCTION_SQL = <<<'SQL'
        CREATE OR REPLACE FUNCTION fn_validar_baja_rol_usuario()
        RETURNS trigger
        LANGUAGE plpgsql
        AS $$
        DECLARE
            v_slug text;
        BEGIN
            SELECT slug INTO v_slug FROM roles WHERE id = OLD.role_id;

            IF v_slug = 'estudiante' AND (
                EXISTS (SELECT 1 FROM usuario_paralelo WHERE fk_usuario = OLD.user_id)
                OR EXISTS (SELECT 1 FROM inscripcion_tutoria WHERE fk_id_usuario = OLD.user_id)
                OR EXISTS (SELECT 1 FROM tema_titulacion WHERE fk_id_usuario = OLD.user_id)
            ) THEN
                RAISE EXCEPTION 'No se puede quitar el rol estudiante: el usuario % tiene registros como estudiante', OLD.user_id;
            END IF;

            IF v_slug = 'docente' AND (
                EXISTS (SELECT 1 FROM asignatura_tutoria WHERE fk_docente = OLD.user_id)
                OR EXISTS (SELECT 1 FROM observacion WHERE fk_docente = OLD.user_id)
                OR EXISTS (SELECT 1 FROM asignacion_docente WHERE fk_id_usuario = OLD.user_id)
                OR EXISTS (SELECT 1 FROM actividad_avance WHERE fk_docente = OLD.user_id)
            ) THEN
                RAISE EXCEPTION 'No se puede quitar el rol docente: el usuario % tiene registros como docente', OLD.user_id;
            END IF;

            IF v_slug = 'coordinador_titulacion' AND (
                EXISTS (SELECT 1 FROM tema_titulacion WHERE fk_coord_revisor = OLD.user_id)
                OR EXISTS (SELECT 1 FROM horario_titulacion WHERE fk_coord_tit = OLD.user_id)
                OR EXISTS (SELECT 1 FROM observacion_titulacion WHERE fk_coord_tit = OLD.user_id)
                OR EXISTS (SELECT 1 FROM informe_titulacion WHERE fk_coord_tit = OLD.user_id)
            ) THEN
                RAISE EXCEPTION 'No se puede quitar el rol coordinador de titulación: el usuario % tiene registros como coordinador', OLD.user_id;
            END IF;

            RETURN OLD;
        END;
        $$;
        SQL;

    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS validar_cambio_rol_usuario ON usuario');
            DB::statement('DROP FUNCTION IF EXISTS fn_validar_cambio_rol_usuario()');

            DB::statement(self::NEW_FUNCTION_SQL);
            DB::statement('DROP TRIGGER IF EXISTS validar_baja_rol_usuario ON role_user');
            DB::statement('CREATE TRIGGER validar_baja_rol_usuario BEFORE DELETE ON role_user FOR EACH ROW EXECUTE FUNCTION fn_validar_baja_rol_usuario()');

            DB::statement('ALTER TABLE usuario DROP CONSTRAINT IF EXISTS usuario_rol_valido');
        }

        Schema::table('usuario', function (Blueprint $table): void {
            $table->dropColumn('rol');
        });
    }

    public function down(): void
    {
        Schema::table('usuario', function (Blueprint $table): void {
            $table->string('rol', 40)->nullable()->after('telefono');
        });

        $primaryRoleByUser = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->orderBy('role_user.assigned_at')
            ->pluck('roles.slug', 'role_user.user_id');

        foreach ($primaryRoleByUser as $userId => $slug) {
            DB::table('usuario')->where('id_usuario', $userId)->update(['rol' => $slug]);
        }

        DB::table('usuario')->whereNull('rol')->update(['rol' => 'estudiante']);

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS validar_baja_rol_usuario ON role_user');
            DB::statement('DROP FUNCTION IF EXISTS fn_validar_baja_rol_usuario()');

            DB::statement("ALTER TABLE usuario ADD CONSTRAINT usuario_rol_valido CHECK (rol IN ('estudiante', 'docente', 'coordinador_carrera', 'coordinador_titulacion', 'administrador'))");
        }

        Schema::table('usuario', function (Blueprint $table): void {
            $table->string('rol', 40)->nullable(false)->change();
        });
    }
};

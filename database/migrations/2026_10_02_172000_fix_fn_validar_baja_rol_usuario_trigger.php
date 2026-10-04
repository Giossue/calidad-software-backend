<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const FUNCTION_SQL = <<<'SQL'
        CREATE OR REPLACE FUNCTION fn_validar_baja_rol_usuario()
        RETURNS trigger
        LANGUAGE plpgsql
        AS $$
        DECLARE
            v_slug text;
            v_table text;
            v_column text;
            v_relation regclass;
            v_has_records boolean;
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
                OR EXISTS (SELECT 1 FROM asignacion_docente WHERE fk_id_usuario = OLD.user_id)
            ) THEN
                RAISE EXCEPTION 'No se puede quitar el rol docente: el usuario % tiene registros como docente', OLD.user_id;
            END IF;

            IF v_slug = 'coordinador_titulacion' AND (
                EXISTS (SELECT 1 FROM tema_titulacion WHERE fk_coord_revisor = OLD.user_id)
                OR EXISTS (SELECT 1 FROM observacion_titulacion WHERE fk_coord_tit = OLD.user_id)
            ) THEN
                RAISE EXCEPTION 'No se puede quitar el rol coordinador de titulación: el usuario % tiene registros como coordinador', OLD.user_id;
            END IF;

            -- Estas tablas no existen en todas las instalaciones y algunas se
            -- crean después de esta migración. Se comprueban en cada DELETE
            -- para conservar la protección cuando estén disponibles.
            FOR v_table, v_column IN
                SELECT dependency.table_name, dependency.column_name
                FROM (VALUES
                    ('docente', 'observacion', 'fk_docente'),
                    ('docente', 'actividad_avance', 'fk_docente'),
                    ('coordinador_titulacion', 'horario_titulacion', 'fk_coord_tit'),
                    ('coordinador_titulacion', 'informe_titulacion', 'fk_coord_tit')
                ) AS dependency(role_slug, table_name, column_name)
                WHERE dependency.role_slug = v_slug
            LOOP
                v_relation := to_regclass(v_table);

                IF v_relation IS NOT NULL THEN
                    EXECUTE format(
                        'SELECT EXISTS (SELECT 1 FROM %s WHERE %I = $1)',
                        v_relation,
                        v_column
                    ) INTO v_has_records USING OLD.user_id;

                    IF v_has_records THEN
                        IF v_slug = 'docente' THEN
                            RAISE EXCEPTION 'No se puede quitar el rol docente: el usuario % tiene registros como docente', OLD.user_id;
                        ELSE
                            RAISE EXCEPTION 'No se puede quitar el rol coordinador de titulación: el usuario % tiene registros como coordinador', OLD.user_id;
                        END IF;
                    END IF;
                END IF;
            END LOOP;

            RETURN OLD;
        END;
        $$;
        SQL;

    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(self::FUNCTION_SQL);
        }
    }

    public function down(): void
    {
        // La corrección conserva las reglas anteriores y evita referencias a
        // tablas ausentes; no se restaura la función incompatible al revertir.
    }
};

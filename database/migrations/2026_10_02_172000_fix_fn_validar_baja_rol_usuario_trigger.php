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
        // No revierte para evitar restaurar referencias a tablas inexistentes.
    }
};
